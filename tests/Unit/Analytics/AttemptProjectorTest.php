<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Analytics;

use MathDeck\Analytics\AttemptProjector;
use MathDeck\Application\StoredEvent;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;
use MathDeck\Engine\Rule\RejectReason;
use MathDeck\Tests\Support\StubPseudonymResolver;
use PHPUnit\Framework\TestCase;

final class AttemptProjectorTest extends TestCase
{
    public function testASolvedAttemptBecomesAFact(): void
    {
        $result = self::projector()->project(self::stream([
            new CardsPlayed('m1', self::at('09:00:01.500000'), 'alice', ['a', 'b', 'c'], '3 + 4', 7, 1500),
            new EquationSolved('m1', self::at('09:00:01.500000'), 'alice', ['a', 'b', 'c'], '3 + 4', 7, 30),
        ]), 'deck-a@1', 0);

        self::assertCount(1, $result['attempts']);

        $attempt = $result['attempts'][0];
        self::assertSame(StubPseudonymResolver::keyOf('alice'), $attempt->studentKey);
        self::assertSame('deck-a@1', $attempt->deckVersionId);
        self::assertTrue($attempt->solved);
        self::assertNull($attempt->reason);
        self::assertSame(30, $attempt->score);
        self::assertSame(1500, $attempt->latencyMs, 'Latency belongs to the play, not the outcome.');
        self::assertSame(3, $attempt->cardCount);
        self::assertSame(2, $result['cursor']);
    }

    public function testARejectedAttemptKeepsItsReasonAndScoresNothing(): void
    {
        $result = self::projector()->project(self::stream([
            new CardsPlayed('m1', self::at('09:00:04.000000'), 'bob', ['a', 'b', 'c'], '3 + 4', 8, 4000),
            new EquationRejected('m1', self::at('09:00:04.000000'), 'bob', ['a', 'b', 'c'], '3 + 4', 8, RejectReason::OffByOne, '7'),
        ]), 'deck-a@1', 0);

        $attempt = $result['attempts'][0];

        self::assertFalse($attempt->solved);
        self::assertSame(RejectReason::OffByOne, $attempt->reason);
        self::assertSame(0, $attempt->score);
    }

    /**
     * Half an attempt is not a fact. If a batch stops between the play and its
     * outcome, the cursor must stay behind it so the next run sees both.
     */
    public function testATrailingPlayIsLeftForTheNextRun(): void
    {
        $result = self::projector()->project(self::stream([
            new CardsPlayed('m1', self::at('09:00:01.000000'), 'alice', ['a'], '3 + 4', 7, 1000),
            new EquationSolved('m1', self::at('09:00:01.000000'), 'alice', ['a'], '3 + 4', 7, 10),
            new TurnEnded('m1', self::at('09:00:01.000000'), 'alice', 1),
            new CardsPlayed('m1', self::at('09:00:02.000000'), 'bob', ['x'], '1 + 1', 7, 2000),
        ]), 'deck-a@1', 0);

        self::assertCount(1, $result['attempts']);
        self::assertSame(3, $result['cursor'], 'Stops at the turn_ended, not at the dangling play.');
    }

    public function testEventsThatProduceNoFactStillMoveTheCursor(): void
    {
        $result = self::projector()->project(self::stream([
            new TargetRevealed('m1', self::at('09:00:00.000000'), 12),
            new TurnEnded('m1', self::at('09:00:00.000000'), 'alice', 1),
        ]), 'deck-a@1', 0);

        self::assertSame([], $result['attempts']);
        self::assertSame(2, $result['cursor']);
    }

    public function testResumingFromACursorKeepsSequenceNumbersTrue(): void
    {
        $events = [
            new StoredEvent(41, new CardsPlayed('m1', self::at('09:00:01.000000'), 'alice', ['a'], '3 + 4', 7, 1000)),
            new StoredEvent(42, new EquationSolved('m1', self::at('09:00:01.000000'), 'alice', ['a'], '3 + 4', 7, 10)),
        ];

        $result = self::projector()->project($events, 'deck-a@1', 40);

        self::assertSame(42, $result['attempts'][0]->seq);
        self::assertSame('m1#42', $result['attempts'][0]->id());
        self::assertSame(42, $result['cursor']);
    }

    public function testAnOutcomeWithoutAPlayIsNotInvented(): void
    {
        $result = self::projector()->project(self::stream([
            new EquationSolved('m1', self::at('09:00:01.000000'), 'alice', ['a'], '3 + 4', 7, 10),
        ]), 'deck-a@1', 0);

        self::assertSame([], $result['attempts']);
    }

    /**
     * Names stop at the projection boundary: what goes into a fact is a pseudonym.
     */
    public function testAttemptsAreStoredUnderAPseudonymNeverAName(): void
    {
        $result = self::projector()->project(self::stream([
            new CardsPlayed('m1', self::at('09:00:01.000000'), 'alice', ['a'], '3 + 4', 7, 1000),
            new EquationSolved('m1', self::at('09:00:01.000000'), 'alice', ['a'], '3 + 4', 7, 10),
        ]), 'deck-a@1', 0);

        self::assertSame(StubPseudonymResolver::keyOf('alice'), $result['attempts'][0]->studentKey);
        self::assertStringNotContainsString(
            'alice',
            json_encode($result['attempts'][0]->toArray(), JSON_THROW_ON_ERROR),
            'A fact must carry no trace of who it belongs to.',
        );
    }

    /**
     * An account that has gone means there is nothing to store this under that is
     * not a name, so the attempt is dropped — but the cursor still moves, or one
     * deleted account would stall a match's projection forever.
     */
    public function testAnAttemptWithNoAccountIsDroppedRatherThanStored(): void
    {
        $projector = new AttemptProjector(new StubPseudonymResolver(withoutAccounts: ['ghost']));

        $result = $projector->project(self::stream([
            new CardsPlayed('m1', self::at('09:00:01.000000'), 'ghost', ['a'], '3 + 4', 7, 1000),
            new EquationSolved('m1', self::at('09:00:01.000000'), 'ghost', ['a'], '3 + 4', 7, 10),
        ]), 'deck-a@1', 0);

        self::assertSame([], $result['attempts']);
        self::assertSame(1, $result['skipped']);
        self::assertSame(2, $result['cursor']);
    }

    private static function projector(): AttemptProjector
    {
        return new AttemptProjector(new StubPseudonymResolver());
    }

    /**
     * @param list<Event> $events
     *
     * @return list<StoredEvent>
     */
    private static function stream(array $events): array
    {
        $stored = [];

        foreach ($events as $index => $event) {
            $stored[] = new StoredEvent($index + 1, $event);
        }

        return $stored;
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-03-01 ' . $time, new \DateTimeZone('UTC'));
    }
}
