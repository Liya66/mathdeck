<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Engine;

use MathDeck\Engine\Random\SeededRandom;
use PHPUnit\Framework\TestCase;

final class SeededRandomTest extends TestCase
{
    public function testTheSameSeedProducesTheSameSequence(): void
    {
        $a = new SeededRandom(7);
        $b = new SeededRandom(7);

        for ($i = 0; $i < 50; ++$i) {
            self::assertSame($a->next(), $b->next());
        }
    }

    public function testDifferentSeedsDiverge(): void
    {
        self::assertNotSame((new SeededRandom(7))->next(), (new SeededRandom(8))->next());
    }

    public function testShuffleIsAPermutation(): void
    {
        $items = range(1, 30);
        $shuffled = (new SeededRandom(99))->shuffle($items);

        self::assertNotSame($items, $shuffled, 'A 30-element shuffle that changes nothing is a bug.');

        sort($shuffled);
        self::assertSame($items, $shuffled);
    }

    public function testValuesStayInsideTheRequestedBound(): void
    {
        $random = new SeededRandom(3);

        for ($i = 0; $i < 500; ++$i) {
            $value = $random->below(6);
            self::assertGreaterThanOrEqual(0, $value);
            self::assertLessThan(6, $value);
        }
    }

    public function testABoundOfZeroIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SeededRandom(1))->below(0);
    }

    /**
     * Guards the choice to draw from the high bits: an LCG's low bits are close to
     * non-random, and a modulo-based bound would skew every shuffle in the game.
     */
    public function testDistributionIsNotObviouslySkewed(): void
    {
        $random = new SeededRandom(2026);
        $counts = array_fill(0, 6, 0);

        for ($i = 0; $i < 6000; ++$i) {
            ++$counts[$random->below(6)];
        }

        foreach ($counts as $face => $count) {
            self::assertGreaterThan(800, $count, sprintf('Face %d came up only %d times.', $face, $count));
            self::assertLessThan(1200, $count, sprintf('Face %d came up %d times.', $face, $count));
        }
    }
}
