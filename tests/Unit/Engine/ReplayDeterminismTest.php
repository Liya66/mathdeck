<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Engine;

use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\Engine;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Engine\State\MatchState;
use MathDeck\Engine\State\Phase;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * The invariant the whole architecture rests on.
 *
 * If these fail, event sourcing is decorative: snapshots cannot be rebuilt, a
 * disputed match cannot be audited, and the analytics projections are describing a
 * game that never happened.
 */
final class ReplayDeterminismTest extends TestCase
{
    private const SEED = 20260115;

    public function testTheSameSeedDealsTheSameMatch(): void
    {
        self::assertSame(
            self::startMatch(self::SEED)->fingerprint(),
            self::startMatch(self::SEED)->fingerprint(),
        );
    }

    public function testADifferentSeedDealsADifferentMatch(): void
    {
        self::assertNotSame(
            self::startMatch(self::SEED)->fingerprint(),
            self::startMatch(self::SEED + 1)->fingerprint(),
        );
    }

    /**
     * Play a match out, then rebuild it from nothing but the seed and the log. The
     * two must be indistinguishable.
     */
    public function testFoldingTheEventLogReproducesTheLivePlayedState(): void
    {
        $initial = self::startMatch(self::SEED);
        $engine = new Engine(new FrozenClock());

        $live = $initial;
        /** @var list<Event> $log */
        $log = [];

        for ($turn = 0; $turn < 12 && $live->phase === Phase::AwaitingPlay; ++$turn) {
            $command = new PlayCards(
                $live->matchId,
                $live->currentPlayer()->id,
                self::firstThreeCardIds($live),
                sprintf('cmd-%d', $turn),
            );

            $events = $engine->handle($live, $command);
            $log = [...$log, ...$events];
            $live = $live->applyAll($events);
        }

        self::assertNotSame([], $log, 'The scripted match produced no events.');
        self::assertSame($live->fingerprint(), $initial->applyAll($log)->fingerprint());
        self::assertSame($live->seq, count($log));
    }

    public function testReplayIsStableAcrossRepeatedRuns(): void
    {
        self::assertSame(self::playScriptedMatch(), self::playScriptedMatch());
    }

    private static function playScriptedMatch(): string
    {
        $state = self::startMatch(self::SEED);
        $engine = new Engine(new FrozenClock());

        for ($turn = 0; $turn < 12 && $state->phase === Phase::AwaitingPlay; ++$turn) {
            $state = $state->applyAll($engine->handle($state, new PlayCards(
                $state->matchId,
                $state->currentPlayer()->id,
                self::firstThreeCardIds($state),
                sprintf('cmd-%d', $turn),
            )));
        }

        return $state->fingerprint();
    }

    /** @return list<string> */
    private static function firstThreeCardIds(MatchState $state): array
    {
        return array_map(
            static fn (Card $card): string => $card->id,
            array_slice($state->currentPlayer()->hand, 0, 3),
        );
    }

    private static function startMatch(int $seed): MatchState
    {
        return MatchState::start(
            matchId: 'match-replay',
            deckVersionId: 'deck-v1',
            seed: $seed,
            rules: DeckRules::default(),
            playerIds: ['alice', 'bob'],
            startedAt: new \DateTimeImmutable('2026-01-15 09:00:00', new \DateTimeZone('UTC')),
        );
    }
}
