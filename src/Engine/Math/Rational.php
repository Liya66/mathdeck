<?php

declare(strict_types=1);

namespace MathDeck\Engine\Math;

use MathDeck\Engine\Exception\ArithmeticOverflow;
use MathDeck\Engine\Exception\DivisionByZero;

/**
 * Exact rational arithmetic.
 *
 * The engine never uses floats. With this deck's operand pool, three- and five-card
 * expressions happen to survive double arithmetic intact — but 261 of the 874,577
 * seven-card expressions do not. `1 / 3 * 7 * 3` is exactly 7 and evaluates to
 * 6.999999999999999, so a learner who answered correctly is told they are wrong.
 *
 * Widening the operand pool, which the deck editor will let teachers do in phase 6,
 * only makes that worse. Exact arithmetic costs nothing here and removes the
 * question entirely.
 *
 * Values are always kept in lowest terms with a positive denominator, so equality
 * is structural.
 */
final readonly class Rational implements \Stringable
{
    public int $numerator;
    public int $denominator;

    public function __construct(int $numerator, int $denominator = 1)
    {
        if ($denominator === 0) {
            throw DivisionByZero::create();
        }

        if ($denominator < 0) {
            $numerator = -$numerator;
            $denominator = -$denominator;
        }

        $divisor = self::gcd(abs($numerator), $denominator);

        $this->numerator = intdiv($numerator, $divisor);
        $this->denominator = intdiv($denominator, $divisor);
    }

    public static function of(int $value): self
    {
        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self(
            self::addExact(
                self::mulExact($this->numerator, $other->denominator),
                self::mulExact($other->numerator, $this->denominator),
            ),
            self::mulExact($this->denominator, $other->denominator),
        );
    }

    public function subtract(self $other): self
    {
        return $this->add($other->negate());
    }

    public function multiply(self $other): self
    {
        return new self(
            self::mulExact($this->numerator, $other->numerator),
            self::mulExact($this->denominator, $other->denominator),
        );
    }

    /**
     * @throws DivisionByZero when the divisor is zero. Callers in the game loop turn
     *                        this into a RejectReason rather than letting it escape.
     */
    public function divide(self $other): self
    {
        if ($other->isZero()) {
            throw DivisionByZero::create();
        }

        return new self(
            self::mulExact($this->numerator, $other->denominator),
            self::mulExact($this->denominator, $other->numerator),
        );
    }

    public function negate(): self
    {
        return new self(-$this->numerator, $this->denominator);
    }

    public function isZero(): bool
    {
        return $this->numerator === 0;
    }

    public function isInteger(): bool
    {
        return $this->denominator === 1;
    }

    public function toInt(): int
    {
        if (!$this->isInteger()) {
            throw new \LogicException(sprintf('%s is not an integer.', $this));
        }

        return $this->numerator;
    }

    public function equals(self $other): bool
    {
        return $this->numerator === $other->numerator
            && $this->denominator === $other->denominator;
    }

    public function equalsInt(int $value): bool
    {
        return $this->denominator === 1 && $this->numerator === $value;
    }

    /**
     * Absolute difference from an integer, used by the misconception classifier
     * to spot off-by-one answers.
     */
    public function distanceFromInt(int $value): self
    {
        $difference = $this->subtract(self::of($value));

        return $difference->numerator < 0 ? $difference->negate() : $difference;
    }

    public function __toString(): string
    {
        return $this->denominator === 1
            ? (string) $this->numerator
            : sprintf('%d/%d', $this->numerator, $this->denominator);
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a === 0 ? 1 : $a;
    }

    /**
     * PHP silently promotes overflowing integer arithmetic to float, which would
     * corrupt a state hash without raising anything. Catch it at the source.
     */
    private static function mulExact(int $a, int $b): int
    {
        $result = $a * $b;

        // PHPStan types int * int as int and has no model of overflow; PHP really
        // does hand back a float here. RationalTest proves it at runtime.
        /** @phpstan-ignore function.alreadyNarrowedType */
        if (!is_int($result)) {
            throw ArithmeticOverflow::in('multiply', $a, $b);
        }

        return $result;
    }

    private static function addExact(int $a, int $b): int
    {
        $result = $a + $b;

        /** @phpstan-ignore function.alreadyNarrowedType */
        if (!is_int($result)) {
            throw ArithmeticOverflow::in('add', $a, $b);
        }

        return $result;
    }
}
