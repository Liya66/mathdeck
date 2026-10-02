<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Analytics;

use MathDeck\Analytics\Attempt;
use MathDeck\Analytics\ReportFilter;
use PHPUnit\Framework\TestCase;

final class ReportFilterTest extends TestCase
{
    public function testAnEmptyFilterMatchesEverything(): void
    {
        self::assertTrue((new ReportFilter())->matches(self::attempt()));
    }

    public function testDeckAndStudentAreBothNarrowing(): void
    {
        $attempt = self::attempt();

        self::assertTrue((new ReportFilter(deckVersionId: 'deck-a@1'))->matches($attempt));
        self::assertFalse((new ReportFilter(deckVersionId: 'deck-b@1'))->matches($attempt));
        self::assertTrue((new ReportFilter(studentKeys: ['key-1', 'key-2']))->matches($attempt));
        self::assertFalse((new ReportFilter(studentKeys: ['key-2']))->matches($attempt));
    }

    /**
     * Both ends are inclusive. A teacher filtering "today" and losing the attempt
     * made at the exact boundary would have no way of telling.
     */
    public function testTheDateRangeIncludesItsEndpoints(): void
    {
        $at = self::moment('2026-03-01 09:00:00');
        $attempt = self::attempt($at);

        self::assertTrue((new ReportFilter(from: $at))->matches($attempt), 'from is inclusive');
        self::assertTrue((new ReportFilter(to: $at))->matches($attempt), 'to is inclusive');

        self::assertFalse((new ReportFilter(from: $at->modify('+1 microsecond')))->matches($attempt));
        self::assertFalse((new ReportFilter(to: $at->modify('-1 microsecond')))->matches($attempt));
    }

    public function testEveryConditionHasToHold(): void
    {
        $attempt = self::attempt();

        self::assertFalse(
            (new ReportFilter(deckVersionId: 'deck-a@1', studentKeys: ['someone-else']))->matches($attempt),
            'Matching the deck is not enough on its own.',
        );
    }

    private static function attempt(?\DateTimeImmutable $at = null): Attempt
    {
        return new Attempt(
            matchId: 'm1',
            seq: 1,
            studentKey: 'key-1',
            deckVersionId: 'deck-a@1',
            target: 7,
            expression: '3 + 4',
            cardCount: 3,
            solved: true,
            reason: null,
            score: 10,
            latencyMs: 1000,
            occurredAt: $at ?? self::moment('2026-03-01 09:00:00'),
        );
    }

    private static function moment(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }
}
