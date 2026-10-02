<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Math;

use MathDeck\Engine\Exception\ArithmeticOverflow;
use MathDeck\Engine\Exception\DivisionByZero;
use MathDeck\Engine\Math\Rational;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RationalTest extends TestCase
{
    /**
     * The reason this class exists, stated as the case that actually breaks.
     *
     * 1 / 3 * 7 * 3 is exactly 7. In doubles it is 6.999999999999999, so a learner
     * who answered correctly would be marked wrong. It is one of 261 seven-card
     * expressions in this deck's operand pool where float arithmetic disagrees with
     * the truth.
     */
    public function testAnExpressionThatFloatArithmeticGetsWrong(): void
    {
        $result = Rational::of(1)
            ->divide(Rational::of(3))
            ->multiply(Rational::of(7))
            ->multiply(Rational::of(3));

        self::assertTrue($result->equalsInt(7), sprintf('Expected exactly 7, got %s.', $result));
        self::assertNotSame(7.0, 1 / 3 * 7 * 3, 'If this ever passes, doubles got better and this test is stale.');
    }

    #[DataProvider('normalisationCases')]
    public function testValuesAreStoredInLowestTerms(
        int $numerator,
        int $denominator,
        int $expectedNumerator,
        int $expectedDenominator,
    ): void {
        $rational = new Rational($numerator, $denominator);

        self::assertSame($expectedNumerator, $rational->numerator);
        self::assertSame($expectedDenominator, $rational->denominator);
    }

    /** @return iterable<string, array{int, int, int, int}> */
    public static function normalisationCases(): iterable
    {
        yield 'already lowest' => [3, 4, 3, 4];
        yield 'common factor' => [6, 8, 3, 4];
        yield 'negative denominator moves to numerator' => [3, -4, -3, 4];
        yield 'double negative cancels' => [-3, -4, 3, 4];
        yield 'zero normalises to 0/1' => [0, 7, 0, 1];
        yield 'integer stays integer' => [12, 4, 3, 1];
    }

    /**
     * @param array{int, int} $left
     * @param array{int, int} $right
     * @param array{int, int} $expected
     */
    #[DataProvider('arithmeticCases')]
    public function testArithmetic(string $operation, array $left, array $right, array $expected): void
    {
        $a = new Rational($left[0], $left[1]);
        $b = new Rational($right[0], $right[1]);

        $result = match ($operation) {
            'add' => $a->add($b),
            'subtract' => $a->subtract($b),
            'multiply' => $a->multiply($b),
            'divide' => $a->divide($b),
            default => self::fail('Unknown operation ' . $operation),
        };

        self::assertTrue(
            $result->equals(new Rational($expected[0], $expected[1])),
            sprintf('Expected %d/%d, got %s.', $expected[0], $expected[1], $result),
        );
    }

    /** @return iterable<string, array{string, array{int, int}, array{int, int}, array{int, int}}> */
    public static function arithmeticCases(): iterable
    {
        yield 'add with unlike denominators' => ['add', [1, 2], [1, 3], [5, 6]];
        yield 'add to a whole number' => ['add', [1, 2], [1, 2], [1, 1]];
        yield 'subtract into negative' => ['subtract', [1, 4], [1, 2], [-1, 4]];
        yield 'multiply' => ['multiply', [2, 3], [3, 4], [1, 2]];
        yield 'divide' => ['divide', [1, 2], [2, 3], [3, 4]];
        yield 'divide by a negative' => ['divide', [1, 2], [-1, 4], [-2, 1]];
    }

    public function testConstructingWithZeroDenominatorIsRefused(): void
    {
        $this->expectException(DivisionByZero::class);

        new Rational(1, 0);
    }

    public function testDividingByZeroIsRefused(): void
    {
        $this->expectException(DivisionByZero::class);

        Rational::of(6)->divide(Rational::zero());
    }

    /**
     * PHP promotes overflowing integer arithmetic to float rather than raising.
     * Left alone, that would corrupt a state fingerprint with no trace.
     */
    public function testOverflowIsRaisedRatherThanSilentlyBecomingAFloat(): void
    {
        $this->expectException(ArithmeticOverflow::class);

        (new Rational(PHP_INT_MAX, 1))->multiply(Rational::of(3));
    }

    public function testToIntRefusesNonIntegers(): void
    {
        $this->expectException(\LogicException::class);

        (new Rational(7, 2))->toInt();
    }

    #[DataProvider('stringCases')]
    public function testStringRepresentation(int $numerator, int $denominator, string $expected): void
    {
        self::assertSame($expected, (string) new Rational($numerator, $denominator));
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function stringCases(): iterable
    {
        yield 'integer' => [4, 1, '4'];
        yield 'fraction' => [7, 2, '7/2'];
        yield 'negative fraction' => [-7, 2, '-7/2'];
    }

    #[DataProvider('distanceCases')]
    public function testDistanceFromIntIsAlwaysPositive(int $numerator, int $denominator, int $target, string $expected): void
    {
        self::assertSame($expected, (string) (new Rational($numerator, $denominator))->distanceFromInt($target));
    }

    /** @return iterable<string, array{int, int, int, string}> */
    public static function distanceCases(): iterable
    {
        yield 'one above' => [8, 1, 7, '1'];
        yield 'one below' => [6, 1, 7, '1'];
        yield 'fractional gap' => [15, 2, 7, '1/2'];
        yield 'exact' => [7, 1, 7, '0'];
    }

    /**
     * Both halves of the comparison matter. With `||` instead of `&&`, 1/2 and 1/3
     * would compare equal — and every report built on exact values would quietly
     * agree with itself.
     */
    public function testEqualityNeedsBothNumeratorAndDenominatorToMatch(): void
    {
        self::assertTrue((new Rational(1, 2))->equals(new Rational(1, 2)));
        self::assertFalse((new Rational(1, 2))->equals(new Rational(1, 3)), 'Same numerator, different denominator.');
        self::assertFalse((new Rational(1, 2))->equals(new Rational(3, 2)), 'Same denominator, different numerator.');
        self::assertTrue((new Rational(2, 4))->equals(new Rational(1, 2)), 'Compared in lowest terms.');
    }

    /**
     * equalsInt is the comparison the whole game turns on — it decides whether a
     * play hit the target. Both halves have to hold: 3/2 is not 3.
     */
    public function testEqualsIntNeedsAWholeNumberAndTheRightOne(): void
    {
        self::assertTrue(Rational::of(7)->equalsInt(7));
        self::assertFalse(Rational::of(7)->equalsInt(8));
        self::assertFalse((new Rational(7, 2))->equalsInt(7), 'A fraction is not the integer above it.');
        self::assertFalse((new Rational(3, 2))->equalsInt(3));
        self::assertTrue((new Rational(14, 2))->equalsInt(7), 'Reduced first, then compared.');
        self::assertTrue(Rational::zero()->equalsInt(0));
    }

    public function testNegateIsPartOfThePublicSurface(): void
    {
        self::assertSame('-3/4', (string) (new Rational(3, 4))->negate());
        self::assertSame('3/4', (string) (new Rational(3, 4))->negate()->negate());
        self::assertSame('0', (string) Rational::zero()->negate());
    }

    public function testIsIntegerAndToInt(): void
    {
        self::assertTrue(Rational::of(5)->isInteger());
        self::assertSame(5, Rational::of(5)->toInt());
        self::assertFalse((new Rational(1, 2))->isInteger());
        self::assertTrue(Rational::zero()->isZero());
    }
}
