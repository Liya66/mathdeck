<?php

declare(strict_types=1);

namespace MathDeck\Engine\Random;

/**
 * A 31-bit linear congruential generator.
 *
 * Deliberately not mt_rand or random_int: this has to produce the same sequence on
 * every platform and every PHP version for as long as matches are replayable, which
 * means owning the algorithm. The constants are the classic glibc ones and the
 * arithmetic stays well inside 64-bit range, so nothing silently promotes to float.
 *
 * Not cryptographically secure, and must never be used where that matters.
 */
final class SeededRandom
{
    private const MODULUS = 2147483648; // 2^31
    private const MULTIPLIER = 1103515245;
    private const INCREMENT = 12345;

    private int $state;

    public function __construct(int $seed)
    {
        $this->state = abs($seed) % self::MODULUS;
    }

    /** @return int<0, 2147483647> */
    public function next(): int
    {
        $this->state = ($this->state * self::MULTIPLIER + self::INCREMENT) % self::MODULUS;

        /** @var int<0, 2147483647> */
        return $this->state;
    }

    /**
     * Uniform in [0, $boundExclusive). Uses the high bits: the low bits of an LCG
     * are notoriously non-random, so modulo would bias the shuffle.
     */
    public function below(int $boundExclusive): int
    {
        if ($boundExclusive < 1) {
            throw new \InvalidArgumentException('Bound must be positive.');
        }

        return (int) (($this->next() / self::MODULUS) * $boundExclusive);
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; --$i) {
            $j = $this->below($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return array_values($items);
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function pick(array $items, int $count): array
    {
        $picked = [];

        for ($i = 0; $i < $count; ++$i) {
            $picked[] = $items[$this->below(count($items))];
        }

        return $picked;
    }
}
