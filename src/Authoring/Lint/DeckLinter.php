<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Lint;

use MathDeck\Authoring\DeckDocument;

/**
 * The publish gate: schema, then the rules the schema cannot express, then balance.
 *
 * Order matters — there is no point asking whether a target is reachable in a deck
 * whose operand list is not a list of numbers.
 */
final readonly class DeckLinter
{
    public function __construct(
        private SchemaValidator $schema,
        private BalanceLinter $balance,
    ) {
    }

    public static function default(): self
    {
        return new self(SchemaValidator::default(), new BalanceLinter());
    }

    /** @param array<string, mixed> $definition */
    public function lint(array $definition): LintReport
    {
        $schemaErrors = $this->schema->validate($definition);

        if ($schemaErrors !== []) {
            return LintReport::schemaFailure($schemaErrors);
        }

        $deck = DeckDocument::fromArray($definition);
        $errors = $this->structuralErrors($deck);
        $warnings = [];
        $targets = $this->balance->inspect($deck);

        foreach ($targets as $report) {
            if ($report->reachable === false) {
                $errors[] = [
                    'code' => 'TARGET_UNREACHABLE',
                    'path' => '/targets',
                    'detail' => sprintf(
                        'No arrangement of these cards makes %d. Add an operand or an operator, or drop the target.',
                        $report->target,
                    ),
                ];

                continue;
            }

            if ($report->reachable === null) {
                $warnings[] = [
                    'code' => 'TARGET_UNVERIFIED',
                    'path' => '/targets',
                    'detail' => sprintf(
                        'Could not check %d within the search budget. The deck can still be published.',
                        $report->target,
                    ),
                ];

                continue;
            }

            if ($report->simpleSolutions === 0) {
                $warnings[] = [
                    'code' => 'TARGET_HARD',
                    'path' => '/targets',
                    'detail' => sprintf(
                        '%d needs at least %d cards — there is no two-number way to make it.',
                        $report->target,
                        $report->shortestCards ?? 0,
                    ),
                ];
            }
        }

        return new LintReport($errors, $warnings, $targets);
    }

    /** @return list<array{code: string, path: string, detail: string}> */
    private function structuralErrors(DeckDocument $deck): array
    {
        $errors = [];
        $minimum = (int) $deck->play('minimumCards', 3);
        $maximum = (int) $deck->play('maximumCards', 7);
        $handSize = (int) $deck->play('handSize', 7);

        if ($maximum < $minimum) {
            $errors[] = [
                'code' => 'CARD_RANGE',
                'path' => '/play/maximumCards',
                'detail' => sprintf('The largest play (%d) cannot be smaller than the smallest (%d).', $maximum, $minimum),
            ];
        }

        if ($handSize < $maximum) {
            $errors[] = [
                'code' => 'HAND_TOO_SMALL',
                'path' => '/play/handSize',
                'detail' => sprintf(
                    'A hand of %d cards can never make a play of %d. Raise the hand size or lower the maximum.',
                    $handSize,
                    $maximum,
                ),
            ];
        }

        return $errors;
    }
}
