<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use MathDeck\Analytics\Attempt;
use MathDeck\Analytics\Port\AttemptStore;
use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Analytics\ReportFilter;
use MathDeck\Engine\Rule\RejectReason;
use PHPUnit\Framework\TestCase;

/**
 * One set of expectations, two implementations.
 *
 * The reports are aggregated in PHP for tests and in SQL in production. A median
 * that differs by one row between them would be invisible, would never fail a unit
 * test, and would quietly give a teacher a different picture of their class
 * depending on which code path answered. So both run this.
 */
abstract class ReportQueriesContract extends TestCase
{
    abstract protected function attemptStore(): AttemptStore;

    abstract protected function reports(): ReportQueries;

    protected function setUp(): void
    {
        $this->attemptStore()->append(self::dataset());
    }

    public function testOverviewOfEverything(): void
    {
        self::assertSame([
            'attempts' => 11,
            'solved' => 5,
            'accuracy' => 0.4545,
            'students' => 3,
            'matches' => 2,
            'totalScore' => 70,
            'medianLatencyMs' => 1000,
        ], $this->reports()->overview(new ReportFilter()));
    }

    public function testOverviewOfAnEmptySelectionIsZeroesAndNotAnError(): void
    {
        self::assertSame([
            'attempts' => 0,
            'solved' => 0,
            'accuracy' => 0.0,
            'students' => 0,
            'matches' => 0,
            'totalScore' => 0,
            'medianLatencyMs' => null,
        ], $this->reports()->overview(new ReportFilter(deckVersionId: 'nothing@1')));
    }

    public function testProgressionIsPerStudentAndOrdered(): void
    {
        $rows = $this->reports()->progression(new ReportFilter());

        self::assertSame(['alice', 'bob', 'carol'], array_column($rows, 'studentKey'));

        self::assertSame([
            'studentKey' => 'alice',
            'attempts' => 5,
            'solved' => 3,
            'accuracy' => 0.6,
            'totalScore' => 60,
            'medianLatencyMs' => 3000,
            'firstSeen' => '2026-03-01T09:00:00.000000Z',
            'lastSeen' => '2026-03-01T09:04:00.000000Z',
        ], $rows[0]);

        self::assertSame(0.25, $rows[1]['accuracy']);
        self::assertSame(200, $rows[1]['medianLatencyMs'], 'Lower median of four values.');
    }

    /**
     * The report the reason-code taxonomy was designed for. Note what is absent:
     * protocol faults never became events, so nothing here is a client bug wearing
     * a misconception's clothes.
     */
    public function testErrorsClusterByMisconceptionMostCommonFirst(): void
    {
        self::assertSame([
            ['reason' => 'OFF_BY_ONE', 'count' => 3, 'share' => 0.5, 'students' => 2],
            ['reason' => 'DIVISION_BY_ZERO', 'count' => 1, 'share' => 0.1667, 'students' => 1],
            ['reason' => 'OPERATOR_PRECEDENCE_IGNORED', 'count' => 1, 'share' => 0.1667, 'students' => 1],
            ['reason' => 'WRONG_TARGET', 'count' => 1, 'share' => 0.1667, 'students' => 1],
        ], $this->reports()->errorDistribution(new ReportFilter()));
    }

    /**
     * Slow-and-right and fast-and-wrong are different problems. Splitting latency by
     * outcome is what makes them distinguishable.
     */
    public function testLatencySplitsByOutcome(): void
    {
        self::assertSame([
            ['bucket' => 'solved', 'count' => 5, 'medianLatencyMs' => 1000, 'p90LatencyMs' => 5000],
            ['bucket' => 'OFF_BY_ONE', 'count' => 3, 'medianLatencyMs' => 200, 'p90LatencyMs' => 3000],
            ['bucket' => 'DIVISION_BY_ZERO', 'count' => 1, 'medianLatencyMs' => 1100, 'p90LatencyMs' => 1100],
            ['bucket' => 'OPERATOR_PRECEDENCE_IGNORED', 'count' => 1, 'medianLatencyMs' => 400, 'p90LatencyMs' => 400],
            ['bucket' => 'WRONG_TARGET', 'count' => 1, 'medianLatencyMs' => 4000, 'p90LatencyMs' => 4000],
        ], $this->reports()->latency(new ReportFilter()));
    }

    public function testFilteringByDeckVersion(): void
    {
        $overview = $this->reports()->overview(new ReportFilter(deckVersionId: 'deck-a@1'));

        self::assertSame(9, $overview['attempts']);
        self::assertSame(4, $overview['solved']);
        self::assertSame(2, $overview['students']);
    }

    public function testFilteringByStudent(): void
    {
        $rows = $this->reports()->progression(new ReportFilter(studentKeys: ['alice', 'carol']));

        self::assertSame(['alice', 'carol'], array_column($rows, 'studentKey'));
    }

    public function testFilteringByDate(): void
    {
        $overview = $this->reports()->overview(new ReportFilter(
            from: new \DateTimeImmutable('2026-03-01 09:10:00', new \DateTimeZone('UTC')),
        ));

        self::assertSame(6, $overview['attempts'], 'Alice played before the window opened.');
        self::assertSame(2, $overview['students']);
    }

    public function testAppendingTheSameFactTwiceDoesNotDoubleIt(): void
    {
        $this->attemptStore()->append(self::dataset());

        self::assertSame(11, $this->reports()->overview(new ReportFilter())['attempts']);
    }

    /** @return list<Attempt> */
    private static function dataset(): array
    {
        $at = static fn (string $time): \DateTimeImmutable => new \DateTimeImmutable(
            '2026-03-01 ' . $time,
            new \DateTimeZone('UTC'),
        );

        return [
            self::attempt('m1', 1, 'alice', 'deck-a@1', true, null, 10, 1000, $at('09:00:00')),
            self::attempt('m1', 2, 'alice', 'deck-a@1', true, null, 20, 2000, $at('09:01:00')),
            self::attempt('m1', 3, 'alice', 'deck-a@1', false, RejectReason::OffByOne, 0, 3000, $at('09:02:00')),
            self::attempt('m1', 4, 'alice', 'deck-a@1', false, RejectReason::WrongTarget, 0, 4000, $at('09:03:00')),
            self::attempt('m1', 5, 'alice', 'deck-a@1', true, null, 30, 5000, $at('09:04:00')),

            self::attempt('m1', 6, 'bob', 'deck-a@1', false, RejectReason::OffByOne, 0, 100, $at('09:10:00')),
            self::attempt('m1', 7, 'bob', 'deck-a@1', false, RejectReason::OffByOne, 0, 200, $at('09:11:00')),
            self::attempt('m1', 8, 'bob', 'deck-a@1', true, null, 10, 300, $at('09:12:00')),
            self::attempt(
                'm1', 9, 'bob', 'deck-a@1', false, RejectReason::OperatorPrecedenceIgnored, 0, 400, $at('09:13:00'),
            ),

            self::attempt('m2', 1, 'carol', 'deck-b@1', true, null, 0, 900, $at('09:20:00')),
            self::attempt('m2', 2, 'carol', 'deck-b@1', false, RejectReason::DivisionByZero, 0, 1100, $at('09:21:00')),
        ];
    }

    private static function attempt(
        string $matchId,
        int $seq,
        string $studentKey,
        string $deckVersionId,
        bool $solved,
        ?RejectReason $reason,
        int $score,
        int $latencyMs,
        \DateTimeImmutable $occurredAt,
    ): Attempt {
        return new Attempt(
            matchId: $matchId,
            seq: $seq,
            studentKey: $studentKey,
            deckVersionId: $deckVersionId,
            target: 12,
            expression: '3 + 4',
            cardCount: 3,
            solved: $solved,
            reason: $reason,
            score: $score,
            latencyMs: $latencyMs,
            occurredAt: $occurredAt,
        );
    }
}
