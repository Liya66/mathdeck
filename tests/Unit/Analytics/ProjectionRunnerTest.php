<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Analytics;

use MathDeck\Analytics\AttemptProjector;
use MathDeck\Analytics\ProjectionRunner;
use MathDeck\Analytics\ReportFilter;
use MathDeck\Application\MatchRecord;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\TurnEnded;
use MathDeck\Engine\Rule\RejectReason;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Infrastructure\InMemory\InMemoryAttemptStore;
use MathDeck\Infrastructure\InMemory\InMemoryEventStore;
use MathDeck\Infrastructure\InMemory\InMemoryMatchStore;
use MathDeck\Infrastructure\InMemory\InMemoryProjectionCursors;
use MathDeck\Tests\Support\StubPseudonymResolver;
use PHPUnit\Framework\TestCase;

final class ProjectionRunnerTest extends TestCase
{
    private InMemoryMatchStore $matches;
    private InMemoryEventStore $events;
    private InMemoryAttemptStore $attempts;
    private InMemoryProjectionCursors $cursors;

    protected function setUp(): void
    {
        $this->matches = new InMemoryMatchStore();
        $this->events = new InMemoryEventStore();
        $this->attempts = new InMemoryAttemptStore();
        $this->cursors = new InMemoryProjectionCursors();
    }

    public function testItProjectsEveryMatchItFinds(): void
    {
        $this->givenMatch('m1', 'deck-a@1');
        $this->givenMatch('m2', 'deck-b@1');
        $this->givenAttempts('m1', 'ada', 2);
        $this->givenAttempts('m2', 'ben', 1);

        $result = $this->runner()->run();

        self::assertSame(['matches' => 2, 'attempts' => 3, 'skipped' => 0], $result);
        self::assertSame(3, $this->attempts->overview(new ReportFilter())['attempts']);
    }

    /** The cursor is what makes a second run cheap instead of duplicated. */
    public function testASecondRunFindsNothingNewToDo(): void
    {
        $this->givenMatch('m1', 'deck-a@1');
        $this->givenAttempts('m1', 'ada', 2);

        $this->runner()->run();
        $second = $this->runner()->run();

        self::assertSame(0, $second['attempts']);
        self::assertSame(2, $this->attempts->overview(new ReportFilter())['attempts']);
    }

    public function testItResumesWhereItLeftOff(): void
    {
        $this->givenMatch('m1', 'deck-a@1');
        $this->givenAttempts('m1', 'ada', 1);
        $this->runner()->run();

        $this->givenAttempts('m1', 'ada', 1);
        $second = $this->runner()->run();

        self::assertSame(1, $second['attempts'], 'Only the new attempt.');
        self::assertSame(2, $this->attempts->overview(new ReportFilter())['attempts']);
    }

    public function testAMatchWithNoEventsIsLeftAlone(): void
    {
        $this->givenMatch('m1', 'deck-a@1');

        self::assertSame(['matches' => 0, 'attempts' => 0, 'skipped' => 0], $this->runner()->run());
    }

    /** A match whose record has gone must not stop the sweep. */
    public function testAMissingMatchRecordIsSkippedRatherThanFatal(): void
    {
        $this->givenMatch('m1', 'deck-a@1');
        $this->givenAttempts('m1', 'ada', 1);
        $this->givenAttempts('ghost-match', 'ada', 1);

        self::assertSame(1, $this->runner()->run()['attempts']);
    }

    public function testAttemptsWithNoAccountAreCountedAsSkipped(): void
    {
        $this->givenMatch('m1', 'deck-a@1');
        $this->givenAttempts('m1', 'ada', 1);
        $this->givenAttempts('m1', 'ghost', 1);

        $result = $this->runner(withoutAccounts: ['ghost'])->run();

        self::assertSame(1, $result['attempts']);
        self::assertSame(1, $result['skipped']);
    }

    public function testTheDeckVersionComesFromTheMatchNotTheEvents(): void
    {
        $this->givenMatch('m1', 'times-tables@7');
        $this->givenAttempts('m1', 'ada', 1);

        $this->runner()->run();

        self::assertSame(1, $this->attempts->overview(new ReportFilter(deckVersionId: 'times-tables@7'))['attempts']);
    }

    /** @param list<string> $withoutAccounts */
    private function runner(array $withoutAccounts = []): ProjectionRunner
    {
        return new ProjectionRunner(
            $this->matches,
            $this->events,
            $this->attempts,
            $this->cursors,
            new AttemptProjector(new StubPseudonymResolver($withoutAccounts)),
        );
    }

    private function givenMatch(string $matchId, string $deckVersionId): void
    {
        $this->matches->save(new MatchRecord(
            matchId: $matchId,
            deckVersionId: $deckVersionId,
            seed: 1,
            playerIds: ['ada', 'ben'],
            rules: DeckRules::default(),
            createdAt: self::at(),
        ));
    }

    private function givenAttempts(string $matchId, string $playerId, int $count): void
    {
        for ($index = 0; $index < $count; ++$index) {
            $solved = $index % 2 === 0;

            /** @var list<Event> $events */
            $events = [
                new CardsPlayed($matchId, self::at(), $playerId, ['a', 'b', 'c'], '3 + 4', 7, 1000),
                $solved
                    ? new EquationSolved($matchId, self::at(), $playerId, ['a', 'b', 'c'], '3 + 4', 7, 10)
                    : new EquationRejected(
                        $matchId, self::at(), $playerId, ['a', 'b', 'c'], '3 + 4', 7, RejectReason::WrongTarget, '8',
                    ),
                new TurnEnded($matchId, self::at(), $playerId, 1),
            ];

            $from = count($this->events->eventsOf($matchId));

            // Command ids must be unique per match, and this helper is called more
            // than once per match, so the index alone will not do.
            $this->events->append($matchId, $from, $events, sprintf('%s-%s-%d', $matchId, $playerId, $from));
        }
    }

    private static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-03-01 09:00:00', new \DateTimeZone('UTC'));
    }
}
