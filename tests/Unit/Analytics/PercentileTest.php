<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Analytics;

use MathDeck\Analytics\Percentile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Two implementations of the reports have to agree on these numbers exactly, so the
 * definition is pinned here rather than left to whichever one is being read.
 */
final class PercentileTest extends TestCase
{
    /** @param list<int> $sorted */
    #[DataProvider('medianCases')]
    public function testMedianIsTheLowerOfTheTwoMiddlesForEvenCounts(array $sorted, ?int $expected): void
    {
        self::assertSame($expected, Percentile::median($sorted));
    }

    /** @return iterable<string, array{list<int>, int|null}> */
    public static function medianCases(): iterable
    {
        yield 'empty' => [[], null];
        yield 'one' => [[7], 7];
        yield 'two takes the lower' => [[10, 20], 10];
        yield 'three' => [[10, 20, 30], 20];
        yield 'four takes the lower middle' => [[10, 20, 30, 40], 20];
        yield 'five' => [[1, 2, 3, 4, 5], 3];
        yield 'ten' => [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 5];
        yield 'repeats' => [[5, 5, 5, 9], 5];
    }

    /** @param list<int> $sorted */
    #[DataProvider('p90Cases')]
    public function testP90(array $sorted, ?int $expected): void
    {
        self::assertSame($expected, Percentile::p90($sorted));
    }

    /** @return iterable<string, array{list<int>, int|null}> */
    public static function p90Cases(): iterable
    {
        yield 'empty' => [[], null];
        yield 'one is its own p90' => [[7], 7];
        yield 'two' => [[10, 20], 20];
        yield 'three' => [[10, 20, 30], 30];
        yield 'ten lands on the ninth' => [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 9];
        yield 'twenty lands on the eighteenth' => [range(1, 20), 18];
        yield 'eleven rounds up' => [range(1, 11), 10];
    }

    /** An index past either end clamps rather than returning null or erroring. */
    public function testIndicesAreClampedToTheData(): void
    {
        self::assertSame(1, Percentile::median([1]));
        self::assertSame(1, Percentile::p90([1]));
        self::assertSame(100, Percentile::p90([100]));
    }
}
