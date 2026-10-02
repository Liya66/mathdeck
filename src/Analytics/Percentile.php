<?php

declare(strict_types=1);

namespace MathDeck\Analytics;

/**
 * One definition of "median", used by every implementation of ReportQueries.
 *
 * Two implementations aggregate this data — PHP in memory and SQL in MySQL — and
 * "median" has more than one reasonable meaning. Pinning it here, as the lower
 * median of the sorted values, is what lets the contract test hold both to the same
 * numbers instead of to approximately the same numbers.
 */
final readonly class Percentile
{
    /** @param list<int> $sorted ascending */
    public static function median(array $sorted): ?int
    {
        return self::at($sorted, intdiv(count($sorted) - 1, 2));
    }

    /** @param list<int> $sorted ascending */
    public static function p90(array $sorted): ?int
    {
        return self::at($sorted, (int) ceil(0.9 * count($sorted)) - 1);
    }

    /** @param list<int> $sorted */
    private static function at(array $sorted, int $index): ?int
    {
        if ($sorted === []) {
            return null;
        }

        return $sorted[max(0, min(count($sorted) - 1, $index))];
    }
}
