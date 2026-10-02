<?php

declare(strict_types=1);

namespace MathDeck\Engine;

use MathDeck\Engine\Command\Command;
use MathDeck\Engine\Command\Forfeit;
use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\MatchEnded;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;
use MathDeck\Engine\Exception\DivisionByZero;
use MathDeck\Engine\Exception\IllegalCommand;
use MathDeck\Engine\Exception\MalformedExpression;
use MathDeck\Engine\Math\Expression;
use MathDeck\Engine\Math\Rational;
use MathDeck\Engine\Rule\CardCountRule;
use MathDeck\Engine\Rule\CardsInHandRule;
use MathDeck\Engine\Rule\MatchInPlayRule;
use MathDeck\Engine\Rule\PlayRule;
use MathDeck\Engine\Rule\RejectReason;
use MathDeck\Engine\Rule\Severity;
use MathDeck\Engine\Rule\TurnOrderRule;
use MathDeck\Engine\Rule\Violation;
use MathDeck\Engine\State\MatchState;
use MathDeck\Engine\State\Phase;

/**
 * The whole engine: (State, Command) -> Event[].
 *
 * No database, no HTTP, no clock but the injected one, no randomness at all — the
 * shuffle was decided by the seed at match start. Given the same state and command
 * this returns the same events on any machine, today or a year from now, which is
 * what makes matches replayable, auditable and cheap to test.
 */
final readonly class Engine
{
    /** @var list<PlayRule> */
    private array $rules;

    /** @param list<PlayRule>|null $rules */
    public function __construct(
        private Clock $clock,
        ?array $rules = null,
    ) {
        $this->rules = $rules ?? self::defaultRules();
    }

    /** @return list<PlayRule> */
    public static function defaultRules(): array
    {
        return [
            new MatchInPlayRule(),
            new TurnOrderRule(),
            new CardsInHandRule(),
            new CardCountRule(),
        ];
    }

    /**
     * @return list<Event>
     *
     * @throws IllegalCommand when a correct client could not have sent this command
     */
    public function handle(MatchState $state, Command $command): array
    {
        return match (true) {
            $command instanceof PlayCards => $this->playCards($state, $command),
            $command instanceof Forfeit => $this->forfeit($state, $command),
            default => throw new \LogicException(sprintf('No handler for %s.', $command::class)),
        };
    }

    /** @return list<Event> */
    private function playCards(MatchState $state, PlayCards $command): array
    {
        $now = $this->clock->now();
        $stream = new EventStream($state);
        $target = $state->board->target;

        foreach ($this->rules as $rule) {
            $violation = $rule->check($state, $command);

            if ($violation === null) {
                continue;
            }

            if ($violation->severity === Severity::Protocol) {
                throw IllegalCommand::because($violation);
            }

            return $this->rejectAttempt($stream, $command, $violation->reason, null, null, $now);
        }

        $cards = $state->currentPlayer()->cardsById($command->cardIds);

        try {
            $expression = Expression::fromCards($cards);
        } catch (MalformedExpression $malformed) {
            return $this->rejectAttempt($stream, $command, $malformed->reason, null, null, $now);
        }

        try {
            $result = $expression->evaluate();
        } catch (DivisionByZero) {
            return $this->rejectAttempt(
                $stream,
                $command,
                RejectReason::DivisionByZero,
                (string) $expression,
                null,
                $now,
            );
        }

        if ($state->rules->requireIntegerResult && !$result->isInteger()) {
            return $this->rejectAttempt(
                $stream,
                $command,
                RejectReason::NonIntegerResult,
                (string) $expression,
                (string) $result,
                $now,
            );
        }

        if (!$result->equalsInt($target)) {
            return $this->rejectAttempt(
                $stream,
                $command,
                $this->classify($expression, $result, $target),
                (string) $expression,
                (string) $result,
                $now,
            );
        }

        return $this->acceptAttempt($stream, $command, $expression, $target, $now);
    }

    /** @return list<Event> */
    private function acceptAttempt(
        EventStream $stream,
        PlayCards $command,
        Expression $expression,
        int $target,
        \DateTimeImmutable $now,
    ): array {
        $state = $stream->state();

        $stream->emit(new CardsPlayed(
            $state->matchId,
            $now,
            $command->playerId(),
            $command->cardIds,
            (string) $expression,
            $target,
            $this->latencyMs($state, $now),
        ));

        $stream->emit(new EquationSolved(
            $state->matchId,
            $now,
            $command->playerId(),
            $command->cardIds,
            (string) $expression,
            $target,
            Scoring::forExpression($expression, $state->rules),
        ));

        $this->refillHand($stream, $command->playerId(), $now);

        return $this->closeTurn($stream, $command, $now);
    }

    /** @return list<Event> */
    private function rejectAttempt(
        EventStream $stream,
        PlayCards $command,
        RejectReason $reason,
        ?string $expression,
        ?string $observedResult,
        \DateTimeImmutable $now,
    ): array {
        $state = $stream->state();
        $target = $state->board->target;

        $stream->emit(new CardsPlayed(
            $state->matchId,
            $now,
            $command->playerId(),
            $command->cardIds,
            $expression,
            $target,
            $this->latencyMs($state, $now),
        ));

        $stream->emit(new EquationRejected(
            $state->matchId,
            $now,
            $command->playerId(),
            $command->cardIds,
            $expression,
            $target,
            $reason,
            $observedResult,
        ));

        // A wrong answer costs the turn but not the cards: the same hand can be
        // tried again next time round, which is the point pedagogically.
        return $this->closeTurn($stream, $command, $now);
    }

    /**
     * Ends the turn, or the match when the board has run out of work to do.
     *
     * @return list<Event>
     */
    private function closeTurn(EventStream $stream, PlayCards $command, \DateTimeImmutable $now): array
    {
        $state = $stream->state();

        if (!$state->board->hasNextTarget()) {
            $stream->emit(new MatchEnded($state->matchId, $now, $state->leaderId(), 'targets_exhausted'));

            return $stream->events();
        }

        if ($state->board->drawPile === []) {
            $stream->emit(new MatchEnded($state->matchId, $now, $state->leaderId(), 'deck_exhausted'));

            return $stream->events();
        }

        $stream->emit(new TargetRevealed($state->matchId, $now, $state->board->nextTarget()));
        $stream->emit(new TurnEnded(
            $state->matchId,
            $now,
            $command->playerId(),
            $state->nextPlayerIndex(),
        ));

        return $stream->events();
    }

    private function refillHand(EventStream $stream, string $playerId, \DateTimeImmutable $now): void
    {
        $state = $stream->state();
        $player = $state->playerById($playerId);

        if ($player === null) {
            return;
        }

        $shortfall = $state->rules->handSize - count($player->hand);
        $available = min($shortfall, count($state->board->drawPile));

        if ($available < 1) {
            return;
        }

        $stream->emit(new CardsDrawn(
            $state->matchId,
            $now,
            $playerId,
            $state->board->peekDraw($available),
        ));
    }

    /**
     * Turn a wrong answer into something a teacher can act on.
     *
     * Order matters: the precedence check comes first because "2 + 3 * 4 = 20" is
     * also off by... nothing in particular, and the specific diagnosis is worth more
     * than the generic one.
     */
    private function classify(Expression $expression, Rational $result, int $target): RejectReason
    {
        if ($expression->precedenceIsSignificant()) {
            try {
                if ($expression->evaluateLeftToRight()->equalsInt($target)) {
                    return RejectReason::OperatorPrecedenceIgnored;
                }
            } catch (DivisionByZero) {
                // Left-to-right reading is not even evaluable; fall through.
            }
        }

        $distance = $result->distanceFromInt($target);

        if ($distance->equalsInt(1)) {
            return RejectReason::OffByOne;
        }

        return RejectReason::WrongTarget;
    }

    /** @return list<Event> */
    private function forfeit(MatchState $state, Forfeit $command): array
    {
        if ($state->phase !== Phase::AwaitingPlay) {
            throw IllegalCommand::because(Violation::protocol(
                RejectReason::MatchNotInPlay,
                sprintf('Match %s is already %s.', $state->matchId, $state->phase->value),
            ));
        }

        $opponent = null;

        foreach ($state->players as $player) {
            if ($player->id !== $command->playerId()) {
                $opponent = $player->id;

                break;
            }
        }

        return [new MatchEnded($state->matchId, $this->clock->now(), $opponent, 'forfeit')];
    }

    /**
     * Measured server-side from the moment the turn opened. A client-reported
     * duration would be both forgeable and, on a school laptop, wrong.
     */
    private function latencyMs(MatchState $state, \DateTimeImmutable $now): int
    {
        $elapsed = (float) $now->format('U.u') - (float) $state->turnStartedAt->format('U.u');

        return max(0, (int) round($elapsed * 1000));
    }
}
