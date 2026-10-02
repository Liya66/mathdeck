<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Authoring;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Authoring\Lint\BalanceLinter;
use MathDeck\Authoring\Lint\TargetReport;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Card\Operator;
use MathDeck\Engine\Math\Expression;
use PHPUnit\Framework\TestCase;

final class BalanceLinterTest extends TestCase
{
    /**
     * The property that makes the linter trustworthy: whenever it says a target is
     * reachable, it hands back an expression, and that expression really does
     * evaluate to the target *in the engine*.
     *
     * The linter's search and the engine's evaluator are separate implementations of
     * the same rules. This is what stops them drifting.
     */
    public function testEveryExampleItOffersIsAcceptedByTheEngine(): void
    {
        $reports = (new BalanceLinter())->inspect(DeckDocument::starter());

        $checked = 0;

        foreach ($reports as $report) {
            if ($report->example === null) {
                continue;
            }

            $expression = Expression::fromCards(self::parse($report->example));

            self::assertTrue(
                $expression->evaluate()->equalsInt($report->target),
                sprintf('Linter claims %s makes %d; the engine disagrees.', $report->example, $report->target),
            );
            self::assertSame($report->shortestCards, count($expression->cards));
            ++$checked;
        }

        self::assertGreaterThan(20, $checked, 'The starter deck should offer plenty of examples.');
    }

    public function testTheStarterDeckIsFullyPlayable(): void
    {
        foreach ((new BalanceLinter())->inspect(DeckDocument::starter()) as $report) {
            self::assertTrue($report->reachable, sprintf('Target %d is not reachable.', $report->target));
            self::assertGreaterThan(0, $report->simpleSolutions);
        }
    }

    /**
     * Even numbers and addition can never make an odd number. A teacher would not
     * spot this; a classroom would.
     */
    public function testAnImpossibleTargetIsProvenImpossible(): void
    {
        $reports = (new BalanceLinter())->inspect(self::deck(
            operands: [2, 4, 6],
            operators: ['+'],
            targets: [6, 7],
        ));

        self::assertTrue(self::forTarget($reports, 6)->reachable);
        self::assertFalse(self::forTarget($reports, 7)->reachable, 'Odd totals are unreachable here.');
    }

    public function testFractionsOnTheWayToAnIntegerAreNotPrunedAway(): void
    {
        // 1 ÷ 3 × 7 × 3 passes through 1/3 and 7/3 before landing exactly on 7. A
        // search that discarded non-integers would call this target impossible.
        $reports = (new BalanceLinter())->inspect(self::deck(
            operands: [1, 3, 7],
            operators: ['*', '/'],
            targets: [7],
            minimumCards: 7,
            maximumCards: 7,
        ));

        $report = self::forTarget($reports, 7);

        self::assertTrue($report->reachable);
        self::assertNotNull($report->example);
        self::assertTrue(Expression::fromCards(self::parse($report->example))->evaluate()->equalsInt(7));
    }

    public function testDividingByZeroIsSkippedRatherThanFatal(): void
    {
        $reports = (new BalanceLinter())->inspect(self::deck(
            operands: [0, 5],
            operators: ['/', '+'],
            targets: [5, 10],
        ));

        self::assertTrue(self::forTarget($reports, 5)->reachable);
        self::assertTrue(self::forTarget($reports, 10)->reachable);
    }

    /**
     * A search that ran out of budget must never be reported as proof that a target
     * is impossible — that would block a teacher from publishing a perfectly good
     * deck, with no way for them to tell why.
     */
    public function testAnExhaustedBudgetReportsUnknownAndNotImpossible(): void
    {
        $reports = (new BalanceLinter(budget: 5))->inspect(self::deck(
            operands: [2, 4, 6],
            operators: ['+'],
            targets: [7],
        ));

        self::assertNull(self::forTarget($reports, 7)->reachable);
    }

    public function testTargetsOutOfCardRangeAreNotCountedAsSimple(): void
    {
        $reports = (new BalanceLinter())->inspect(self::deck(
            operands: [2, 3],
            operators: ['+'],
            targets: [5],
            minimumCards: 5,
            maximumCards: 5,
        ));

        // 2 + 3 makes 5 but is only three cards, which this deck does not allow.
        $report = self::forTarget($reports, 5);

        self::assertSame(0, $report->simpleSolutions);
        self::assertFalse($report->reachable, '5 needs three operands here, and 2+3+? overshoots.');
    }

    /**
     * @param list<int>    $operands
     * @param list<string> $operators
     * @param list<int>    $targets
     */
    private static function deck(
        array $operands,
        array $operators,
        array $targets,
        int $minimumCards = 3,
        int $maximumCards = 7,
    ): DeckDocument {
        return DeckDocument::fromArray([
            'schemaVersion' => 1,
            'name' => 'Test deck',
            'operands' => ['values' => $operands, 'copies' => 4],
            'operators' => ['symbols' => $operators, 'copies' => 8],
            'targets' => $targets,
            'play' => [
                'handSize' => 9,
                'minimumCards' => $minimumCards,
                'maximumCards' => $maximumCards,
                'targetsPerMatch' => 5,
                'requireIntegerResult' => true,
                'baseScore' => 10,
            ],
        ]);
    }

    /** @param list<TargetReport> $reports */
    private static function forTarget(array $reports, int $target): TargetReport
    {
        foreach ($reports as $report) {
            if ($report->target === $target) {
                return $report;
            }
        }

        self::fail(sprintf('No report for target %d.', $target));
    }

    /** @return list<Card> */
    private static function parse(string $expression): array
    {
        $cards = [];

        foreach (preg_split('/\s+/', trim($expression), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $index => $token) {
            $id = sprintf('c%d', $index);
            $cards[] = is_numeric($token)
                ? Card::operand($id, (int) $token)
                : Card::operator($id, Operator::from($token));
        }

        return $cards;
    }
}
