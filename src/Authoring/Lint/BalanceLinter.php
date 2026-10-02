<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Lint;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Engine\Exception\ArithmeticOverflow;
use MathDeck\Engine\Exception\DivisionByZero;
use MathDeck\Engine\Math\Rational;

/**
 * Can each target actually be made from this deck's cards?
 *
 * Schema validity says a deck is well-formed. It says nothing about whether the
 * deck is playable, and an unreachable target is invisible until a classroom of
 * children cannot solve it.
 *
 * The search exploits the fact that expressions here are flat and unparenthesised.
 * With precedence, any such expression is a sum and difference of *terms*, where a
 * term is a chain of × and ÷. So:
 *
 *     term(1) = the operand pool
 *     term(k) = term(k-1) ∘ operand,  ∘ ∈ {×, ÷}
 *     expr(k) = term(k) ∪ ⋃ⱼ { expr(k-j) ± term(j) }
 *
 * Both chains are left-associative, which is exactly how the engine evaluates them,
 * so the two cannot disagree.
 *
 * Fractions are kept exactly and never pruned for being non-integer: 1 ÷ 3 × 7 × 3
 * reaches 7 through a value that is not an integer on the way.
 */
final readonly class BalanceLinter
{
    private const DEFAULT_BUDGET = 2_000_000;
    private const NUMERATOR_CAP = 10_000_000;
    private const DENOMINATOR_CAP = 100_000;

    public function __construct(private int $budget = self::DEFAULT_BUDGET)
    {
    }

    /** @return list<TargetReport> */
    public function inspect(DeckDocument $deck): array
    {
        $pool = array_values(array_unique($deck->operandValues()));
        $symbols = $deck->operatorSymbols();
        $high = array_values(array_intersect($symbols, ['*', '/']));
        $low = array_values(array_intersect($symbols, ['+', '-']));

        $minOperands = max(1, $deck->minimumOperands());
        $maxOperands = max($minOperands, $deck->maximumOperands());

        if ($pool === [] || $symbols === []) {
            return array_map(static fn (int $t): TargetReport => TargetReport::unknown($t), $deck->targets());
        }

        $spent = 0;
        $exhaustive = true;

        $terms = [1 => self::seed($pool)];

        for ($k = 2; $k <= $maxOperands; ++$k) {
            $terms[$k] = $high === []
                ? []
                : $this->extend($terms[$k - 1], $pool, $high, $spent, $exhaustive);
        }

        $expressions = [1 => $terms[1]];

        for ($k = 2; $k <= $maxOperands; ++$k) {
            $set = $terms[$k];

            if ($low !== []) {
                for ($j = 1; $j < $k; ++$j) {
                    $set += $this->join($expressions[$k - $j], $terms[$j], $low, $spent, $exhaustive);
                }
            }

            $expressions[$k] = $set;
        }

        return array_map(
            fn (int $target): TargetReport => $this->reportOn(
                $target,
                $expressions,
                $minOperands,
                $maxOperands,
                $pool,
                $symbols,
                $exhaustive,
            ),
            $deck->targets(),
        );
    }

    /**
     * @param array<int, array<string, array{r: Rational, expr: string}>> $expressions
     * @param list<int>                                                   $pool
     * @param list<string>                                                $symbols
     */
    private function reportOn(
        int $target,
        array $expressions,
        int $minOperands,
        int $maxOperands,
        array $pool,
        array $symbols,
        bool $exhaustive,
    ): TargetReport {
        $key = $target . '/1';
        $shortest = null;
        $example = null;

        for ($k = $minOperands; $k <= $maxOperands; ++$k) {
            if (isset($expressions[$k][$key])) {
                $shortest = 2 * $k - 1;
                $example = $expressions[$k][$key]['expr'];

                break;
            }
        }

        $simple = $minOperands <= 2 && $maxOperands >= 2
            ? $this->countTwoOperandSolutions($pool, $symbols, $target)
            : 0;

        // Never claim "impossible" on the strength of a search that gave up.
        $reachable = $shortest !== null ? true : ($exhaustive ? false : null);

        return new TargetReport($target, $reachable, $shortest, $simple, $example);
    }

    /**
     * @param list<int>    $pool
     * @param list<string> $symbols
     */
    private function countTwoOperandSolutions(array $pool, array $symbols, int $target): int
    {
        $count = 0;

        foreach ($pool as $left) {
            foreach ($pool as $right) {
                foreach ($symbols as $symbol) {
                    try {
                        if (self::apply($symbol, Rational::of($left), Rational::of($right))->equalsInt($target)) {
                            ++$count;
                        }
                    } catch (DivisionByZero | ArithmeticOverflow) {
                        continue;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * @param list<int> $pool
     *
     * @return array<string, array{r: Rational, expr: string}>
     */
    private static function seed(array $pool): array
    {
        $seeded = [];

        foreach ($pool as $value) {
            $rational = Rational::of($value);
            $seeded[self::keyOf($rational)] ??= ['r' => $rational, 'expr' => (string) $value];
        }

        return $seeded;
    }

    /**
     * @param array<string, array{r: Rational, expr: string}> $base
     * @param list<int>                                       $pool
     * @param list<string>                                    $operators
     *
     * @return array<string, array{r: Rational, expr: string}>
     */
    private function extend(array $base, array $pool, array $operators, int &$spent, bool &$exhaustive): array
    {
        $extended = [];

        foreach ($base as $entry) {
            foreach ($pool as $value) {
                foreach ($operators as $operator) {
                    if (++$spent > $this->budget) {
                        $exhaustive = false;

                        return $extended;
                    }

                    $this->record($extended, $entry, ['r' => Rational::of($value), 'expr' => (string) $value], $operator, $exhaustive);
                }
            }
        }

        return $extended;
    }

    /**
     * @param array<string, array{r: Rational, expr: string}> $left
     * @param array<string, array{r: Rational, expr: string}> $right
     * @param list<string>                                    $operators
     *
     * @return array<string, array{r: Rational, expr: string}>
     */
    private function join(array $left, array $right, array $operators, int &$spent, bool &$exhaustive): array
    {
        $joined = [];

        foreach ($left as $a) {
            foreach ($right as $b) {
                foreach ($operators as $operator) {
                    if (++$spent > $this->budget) {
                        $exhaustive = false;

                        return $joined;
                    }

                    $this->record($joined, $a, $b, $operator, $exhaustive);
                }
            }
        }

        return $joined;
    }

    /**
     * @param array<string, array{r: Rational, expr: string}> $into
     * @param array{r: Rational, expr: string}                $a
     * @param array{r: Rational, expr: string}                $b
     */
    private function record(array &$into, array $a, array $b, string $operator, bool &$exhaustive): void
    {
        try {
            $value = self::apply($operator, $a['r'], $b['r']);
        } catch (DivisionByZero) {
            return; // Not a gap in the search: the expression is simply not legal.
        } catch (ArithmeticOverflow) {
            $exhaustive = false;

            return;
        }

        if (abs($value->numerator) > self::NUMERATOR_CAP || $value->denominator > self::DENOMINATOR_CAP) {
            // Dropped for size, so the search is no longer a proof of impossibility.
            $exhaustive = false;

            return;
        }

        $into[self::keyOf($value)] ??= ['r' => $value, 'expr' => $a['expr'] . ' ' . $operator . ' ' . $b['expr']];
    }

    private static function apply(string $operator, Rational $left, Rational $right): Rational
    {
        return match ($operator) {
            '+' => $left->add($right),
            '-' => $left->subtract($right),
            '*' => $left->multiply($right),
            '/' => $left->divide($right),
            default => throw new \LogicException(sprintf('Unknown operator "%s".', $operator)),
        };
    }

    private static function keyOf(Rational $value): string
    {
        return $value->numerator . '/' . $value->denominator;
    }
}
