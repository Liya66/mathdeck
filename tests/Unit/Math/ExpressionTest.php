<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Math;

use MathDeck\Engine\Exception\DivisionByZero;
use MathDeck\Engine\Exception\MalformedExpression;
use MathDeck\Engine\Math\Expression;
use MathDeck\Engine\Rule\RejectReason;
use MathDeck\Tests\Support\Cards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpressionTest extends TestCase
{
    #[DataProvider('precedenceCases')]
    public function testEvaluationHonoursPrecedence(string $specification, string $expected): void
    {
        $expression = Expression::fromCards(Cards::parse($specification));

        self::assertSame($expected, (string) $expression->evaluate());
    }

    /** @return iterable<string, array{string, string}> */
    public static function precedenceCases(): iterable
    {
        yield 'multiplication before addition' => ['2 + 3 * 4', '14'];
        yield 'multiplication first is unaffected' => ['3 * 4 + 2', '14'];
        yield 'division before subtraction' => ['10 - 6 / 2', '7'];
        yield 'left to right within a precedence level' => ['10 - 3 - 2', '5'];
        yield 'chained multiplication' => ['2 * 3 * 4', '24'];
        yield 'exact fraction survives' => ['1 / 3 * 3', '1'];
        yield 'non-integer result is kept exact' => ['7 / 2', '7/2'];
        yield 'mixed with two low then high' => ['1 + 2 + 3 * 4', '15'];
    }

    public function testLeftToRightEvaluationIgnoresPrecedence(): void
    {
        $expression = Expression::fromCards(Cards::parse('2 + 3 * 4'));

        self::assertSame('14', (string) $expression->evaluate());
        self::assertSame('20', (string) $expression->evaluateLeftToRight());
    }

    #[DataProvider('malformedCases')]
    public function testMalformedSequencesAreRefusedAtConstruction(string $specification, RejectReason $expected): void
    {
        try {
            Expression::fromCards(Cards::parse($specification));
            self::fail(sprintf('Expected "%s" to be refused.', $specification));
        } catch (MalformedExpression $malformed) {
            self::assertSame($expected, $malformed->reason);
        }
    }

    /** @return iterable<string, array{string, RejectReason}> */
    public static function malformedCases(): iterable
    {
        yield 'two operands in a row' => ['3 4 +', RejectReason::Malformed];
        yield 'leading operator' => ['+ 3 4', RejectReason::Malformed];
        yield 'trailing operator' => ['3 + 4 +', RejectReason::Malformed];
        yield 'two cards is below the minimum' => ['3 +', RejectReason::TooFewCards];
        yield 'single operand' => ['3', RejectReason::TooFewCards];
        yield 'empty' => ['', RejectReason::TooFewCards];
    }

    /**
     * Both halves of the alternation check have to fire. A version that only
     * rejected an operand-where-an-operator-belongs would accept "3 + +".
     */
    public function testAnOperatorWhereAnOperandBelongsIsRefused(): void
    {
        $this->expectException(MalformedExpression::class);

        Expression::fromCards(Cards::parse('+ + +'));
    }

    public function testDivisionByZeroEscapesEvaluation(): void
    {
        $expression = Expression::fromCards(Cards::parse('6 / 0'));

        $this->expectException(DivisionByZero::class);

        $expression->evaluate();
    }

    #[DataProvider('significanceCases')]
    public function testPrecedenceSignificanceDetection(string $specification, bool $expected): void
    {
        $expression = Expression::fromCards(Cards::parse($specification));

        self::assertSame($expected, $expression->precedenceIsSignificant());
    }

    /**
     * The guard that stops the misconception classifier crying wolf: in "2 * 3 + 1"
     * both readings agree, so a wrong answer there is not a precedence error.
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function significanceCases(): iterable
    {
        yield 'low before high matters' => ['2 + 3 * 4', true];
        yield 'high before low does not' => ['2 * 3 + 4', false];
        yield 'all one level does not' => ['2 + 3 + 4', false];
        yield 'all high does not' => ['2 * 3 * 4', false];
        yield 'later high after low matters' => ['1 + 2 - 3 / 4', true];
    }

    public function testExpressionReportsItsShape(): void
    {
        $expression = Expression::fromCards(Cards::parse('2 + 3 * 4'));

        self::assertSame(2, $expression->operatorCount());
        self::assertTrue($expression->usesHighPrecedenceOperator());
        self::assertSame('2 + 3 * 4', (string) $expression);
        self::assertSame(['h0', 'h1', 'h2', 'h3', 'h4'], $expression->cardIds());

        self::assertFalse(Expression::fromCards(Cards::parse('2 + 3'))->usesHighPrecedenceOperator());
    }
}
