<?php

declare(strict_types=1);

namespace MathDeck\Engine\State;

use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\MatchEnded;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;
use MathDeck\Engine\Random\SeededRandom;

/**
 * The fold of the event log.
 *
 * apply() is the only way state changes, and it is total: every event type has a
 * case, and adding an event without a case here is a fatal error rather than a
 * silent no-op. Persistence can therefore be a snapshot cache and nothing more.
 */
final readonly class MatchState
{
    /** @param list<PlayerState> $players */
    public function __construct(
        public string $matchId,
        public string $deckVersionId,
        public int $seed,
        public DeckRules $rules,
        public Phase $phase,
        public int $seq,
        public array $players,
        public int $currentPlayerIndex,
        public Board $board,
        public \DateTimeImmutable $turnStartedAt,
        public ?string $winnerId = null,
    ) {
    }

    /**
     * The only place randomness is used, and it is fully determined by $seed.
     *
     * @param list<string> $playerIds
     */
    public static function start(
        string $matchId,
        string $deckVersionId,
        int $seed,
        DeckRules $rules,
        array $playerIds,
        \DateTimeImmutable $startedAt,
    ): self {
        if (count($playerIds) < 2) {
            throw new \InvalidArgumentException('A match needs at least two players.');
        }

        $random = new SeededRandom($seed);
        $deck = $random->shuffle(self::buildDeck($rules));

        $players = [];
        $offset = 0;

        foreach ($playerIds as $playerId) {
            $players[] = new PlayerState($playerId, array_slice($deck, $offset, $rules->handSize));
            $offset += $rules->handSize;
        }

        $targets = $random->pick($rules->targetPool, $rules->targetsPerMatch);
        $firstTarget = array_shift($targets);

        if ($firstTarget === null) {
            throw new \InvalidArgumentException('A deck must provide at least one target.');
        }

        return new self(
            matchId: $matchId,
            deckVersionId: $deckVersionId,
            seed: $seed,
            rules: $rules,
            phase: Phase::AwaitingPlay,
            seq: 0,
            players: $players,
            currentPlayerIndex: 0,
            board: new Board(
                target: $firstTarget,
                drawPile: array_values(array_slice($deck, $offset)),
                targetQueue: array_values($targets),
            ),
            turnStartedAt: $startedAt,
        );
    }

    public function apply(Event $event): self
    {
        return match (true) {
            // An attempt is telemetry, not a state change. Cards leave the hand only
            // when the equation actually stands.
            $event instanceof CardsPlayed, $event instanceof EquationRejected => $this->next(),

            $event instanceof EquationSolved => $this->next(
                players: $this->replacePlayer(
                    $event->playerId,
                    static fn (PlayerState $player): PlayerState => $player
                        ->withoutCards($event->cardIds)
                        ->withScoreIncreasedBy($event->score),
                ),
            ),

            $event instanceof CardsDrawn => $this->next(
                players: $this->replacePlayer(
                    $event->playerId,
                    static fn (PlayerState $player): PlayerState => $player->withCards($event->cards),
                ),
                board: $this->board->withCardsDrawn(count($event->cards)),
            ),

            $event instanceof TargetRevealed => $this->next(board: $this->board->withTargetTaken()),

            $event instanceof TurnEnded => $this->next(
                currentPlayerIndex: $event->nextPlayerIndex,
                turnStartedAt: $event->occurredAt(),
            ),

            $event instanceof MatchEnded => $this->next(
                phase: Phase::Ended,
                winnerId: $event->winnerId,
            ),

            default => throw new \LogicException(sprintf('No reducer case for %s.', $event::class)),
        };
    }

    /** @param iterable<Event> $events */
    public function applyAll(iterable $events): self
    {
        $state = $this;

        foreach ($events as $event) {
            $state = $state->apply($event);
        }

        return $state;
    }

    public function currentPlayer(): PlayerState
    {
        return $this->players[$this->currentPlayerIndex]
            ?? throw new \LogicException('Current player index is out of range.');
    }

    public function isCurrentPlayer(string $playerId): bool
    {
        return $this->currentPlayer()->id === $playerId;
    }

    public function playerById(string $playerId): ?PlayerState
    {
        foreach ($this->players as $player) {
            if ($player->id === $playerId) {
                return $player;
            }
        }

        return null;
    }

    public function nextPlayerIndex(): int
    {
        return ($this->currentPlayerIndex + 1) % count($this->players);
    }

    /** Highest score, or null when the match is drawn. */
    public function leaderId(): ?string
    {
        $best = null;
        $drawn = false;

        foreach ($this->players as $player) {
            if ($best === null || $player->score > $best->score) {
                $best = $player;
                $drawn = false;
            } elseif ($player->score === $best->score) {
                $drawn = true;
            }
        }

        return $drawn ? null : $best?->id;
    }

    /**
     * Structural fingerprint, used by the replay test to assert that folding a log
     * reproduces a match exactly. Deliberately excludes wall-clock fields.
     */
    public function fingerprint(): string
    {
        $shape = [
            'phase' => $this->phase->value,
            'seq' => $this->seq,
            'currentPlayerIndex' => $this->currentPlayerIndex,
            'winnerId' => $this->winnerId,
            'target' => $this->board->target,
            'targetQueue' => $this->board->targetQueue,
            'drawPile' => array_map(static fn (Card $card): string => $card->id, $this->board->drawPile),
            'players' => array_map(
                static fn (PlayerState $player): array => [
                    'id' => $player->id,
                    'score' => $player->score,
                    'hand' => array_map(static fn (Card $card): string => $card->id, $player->hand),
                ],
                $this->players,
            ),
        ];

        return hash('sha256', json_encode($shape, JSON_THROW_ON_ERROR));
    }

    /** @return list<Card> */
    private static function buildDeck(DeckRules $rules): array
    {
        $deck = [];
        $index = 0;

        foreach ($rules->operandPool as $value) {
            for ($copy = 0; $copy < $rules->operandCopies; ++$copy) {
                $deck[] = Card::operand(sprintf('c%03d', $index++), $value);
            }
        }

        foreach ($rules->operators as $operator) {
            for ($copy = 0; $copy < $rules->operatorCopies; ++$copy) {
                $deck[] = Card::operator(sprintf('c%03d', $index++), $operator);
            }
        }

        return $deck;
    }

    /**
     * @param callable(PlayerState): PlayerState $change
     *
     * @return list<PlayerState>
     */
    private function replacePlayer(string $playerId, callable $change): array
    {
        return array_map(
            static fn (PlayerState $player): PlayerState => $player->id === $playerId
                ? $change($player)
                : $player,
            $this->players,
        );
    }

    /**
     * Null means "unchanged". winnerId is only ever set, never cleared, so it is
     * safe to express that way too.
     *
     * @param list<PlayerState>|null $players
     */
    private function next(
        ?Phase $phase = null,
        ?array $players = null,
        ?int $currentPlayerIndex = null,
        ?Board $board = null,
        ?\DateTimeImmutable $turnStartedAt = null,
        ?string $winnerId = null,
    ): self {
        return new self(
            matchId: $this->matchId,
            deckVersionId: $this->deckVersionId,
            seed: $this->seed,
            rules: $this->rules,
            phase: $phase ?? $this->phase,
            seq: $this->seq + 1,
            players: $players ?? $this->players,
            currentPlayerIndex: $currentPlayerIndex ?? $this->currentPlayerIndex,
            board: $board ?? $this->board,
            turnStartedAt: $turnStartedAt ?? $this->turnStartedAt,
            winnerId: $winnerId ?? $this->winnerId,
        );
    }
}
