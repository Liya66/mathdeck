<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Analytics;

use MathDeck\Analytics\Attempt;
use MathDeck\Engine\Rule\RejectReason;
use PHPUnit\Framework\TestCase;

final class AttemptTest extends TestCase
{
    /**
     * Pins the shape field by field. This is what the projection writes and what
     * every report is built from, so a field silently going missing would show up
     * as a report that is simply a bit wrong.
     */
    public function testASolvedAttemptSerialisesEveryField(): void
    {
        self::assertSame([
            'matchId' => 'm1',
            'seq' => 12,
            'studentKey' => 'abc123',
            'deckVersionId' => 'starter@1',
            'target' => 14,
            'expression' => '2 + 3 * 4',
            'cardCount' => 5,
            'solved' => true,
            'reason' => null,
            'score' => 30,
            'latencyMs' => 4200,
            'occurredAt' => '2026-03-01T09:00:00.000000Z',
        ], self::attempt(solved: true, reason: null, score: 30)->toArray());
    }

    public function testARejectedAttemptCarriesItsReasonAndNoScore(): void
    {
        $row = self::attempt(solved: false, reason: RejectReason::OffByOne, score: 0)->toArray();

        self::assertFalse($row['solved']);
        self::assertSame('OFF_BY_ONE', $row['reason']);
        self::assertSame(0, $row['score']);
    }

    /** The fact's identity, and why re-running the worker is safe. */
    public function testIdentityIsTheMatchAndSequence(): void
    {
        self::assertSame('m1#12', self::attempt(true, null, 30)->id());
    }

    private static function attempt(bool $solved, ?RejectReason $reason, int $score): Attempt
    {
        return new Attempt(
            matchId: 'm1',
            seq: 12,
            studentKey: 'abc123',
            deckVersionId: 'starter@1',
            target: 14,
            expression: '2 + 3 * 4',
            cardCount: 5,
            solved: $solved,
            reason: $reason,
            score: $score,
            latencyMs: 4200,
            occurredAt: new \DateTimeImmutable('2026-03-01 09:00:00', new \DateTimeZone('UTC')),
        );
    }
}
