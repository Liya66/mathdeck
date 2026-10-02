<?php

declare(strict_types=1);

namespace MathDeck\Engine\Math;

use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Card\Operator;
use MathDeck\Engine\Exception\MalformedExpression;
use MathDeck\Engine\Rule\RejectReason;

/**
 * A well-formed infix expression built from a sequence of cards.
 *
 * Well-formed means strictly alternating operand/operator, starting and ending on
 * an operand, which makes the length necessarily odd. Construction is the only
 * validation point: if you hold an Expression, it parses.
 */
final readonly class Expression implements \Stringable
{
    private const MINIMUM_CARDS = 3;

    /** @param list<Card> $cards */
    private function __construct(public array $cards)
    {
    }

    /**
     * @param list<Card> $cards
     *
     * @throws MalformedExpression
     */
    public static function fromCards(array $cards): self
    {
        $count = count($cards);

        if ($count < self::MINIMUM_CARDS) {
            throw MalformedExpression::because(
                RejectReason::TooFewCards,
                sprintf('An expression needs at least %d cards, got %d.', self::MINIMUM_CARDS, $count),
            );
        }

        if ($count % 2 === 0) {
            throw MalformedExpression::because(
                RejectReason::Malformed,
                sprintf('An alternating expression has an odd number of cards, got %d.', $count),
            );
        }

        foreach ($cards as $index => $card) {
            $expectsOperand = $index % 2 === 0;

            if ($expectsOperand && !$card->isOperand()) {
                throw MalformedExpression::because(
                    RejectReason::Malformed,
                    sprintf('Expected an operand at position %d, got "%s".', $index, $card),
                );
            }

            if (!$expectsOperand && !$card->isOperator()) {
                throw MalformedExpression::because(
                    RejectReason::Malformed,
                    sprintf('Expected an operator at position %d, got "%s".', $index, $card),
                );
            }
        }

        return new self($cards);
    }

    /**
     * Evaluate honouring operator precedence. This is the real answer.
     */
    public function evaluate(): Rational
    {
        return $this->fold(respectPrecedence: true);
    }

    /**
     * Evaluate strictly left to right. Never the real answer: this exists so the
     * engine can recognise the specific mistake of ignoring precedence and report
     * it as such instead of a flat "wrong".
     */
    public function evaluateLeftToRight(): Rational
    {
        return $this->fold(respectPrecedence: false);
    }

    /** @return list<string> */
    public function cardIds(): array
    {
        return array_map(static fn (Card $card): string => $card->id, $this->cards);
    }

    public function operatorCount(): int
    {
        return intdiv(count($this->cards), 2);
    }

    public function usesHighPrecedenceOperator(): bool
    {
        foreach ($this->operators() as $operator) {
            if ($operator->precedence() > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when precedence actually matters here, i.e. a lower-precedence operator
     * appears before a higher-precedence one. Without this guard, "2 * 3 + 1"
     * would be misreported as a precedence misconception: both readings agree.
     */
    public function precedenceIsSignificant(): bool
    {
        $seenLowPrecedence = false;

        foreach ($this->operators() as $operator) {
            if ($operator->precedence() === 1) {
                $seenLowPrecedence = true;
            } elseif ($seenLowPrecedence) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return implode(' ', array_map(static fn (Card $card): string => (string) $card, $this->cards));
    }

    /** @return list<Operator> */
    private function operators(): array
    {
        $operators = [];

        foreach ($this->cards as $index => $card) {
            if ($index % 2 === 1) {
                $operators[] = $card->operatorValue();
            }
        }

        return $operators;
    }

    private function fold(bool $respectPrecedence): Rational
    {
        /** @var list<Rational> $operands */
        $operands = [];
        /** @var list<Operator> $operators */
        $operators = [];

        foreach ($this->cards as $index => $card) {
            if ($index % 2 === 0) {
                $operands[] = Rational::of($card->operandValue());
            } else {
                $operators[] = $card->operatorValue();
            }
        }

        if ($respectPrecedence) {
            [$operands, $operators] = self::reduceHighPrecedence($operands, $operators);
        }

        $accumulator = $operands[0];

        foreach ($operators as $index => $operator) {
            $accumulator = $operator->apply($accumulator, $operands[$index + 1]);
        }

        return $accumulator;
    }

    /**
     * @param list<Rational> $operands
     * @param list<Operator> $operators
     *
     * @return array{0: list<Rational>, 1: list<Operator>}
     */
    private static function reduceHighPrecedence(array $operands, array $operators): array
    {
        $reducedOperands = [$operands[0]];
        $reducedOperators = [];

        foreach ($operators as $index => $operator) {
            $right = $operands[$index + 1];

            if ($operator->precedence() > 1) {
                $left = array_pop($reducedOperands);
                assert($left instanceof Rational);
                $reducedOperands[] = $operator->apply($left, $right);

                continue;
            }

            $reducedOperators[] = $operator;
            $reducedOperands[] = $right;
        }

        return [$reducedOperands, $reducedOperators];
    }
}
