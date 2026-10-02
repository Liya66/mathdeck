<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Exception;

use MathDeck\Authoring\Lint\LintReport;

final class DeckRejected extends \RuntimeException
{
    private function __construct(public readonly LintReport $report, string $message)
    {
        parent::__construct($message);
    }

    public static function from(LintReport $report): self
    {
        return new self($report, sprintf(
            'This deck cannot be published: %s',
            implode(' ', array_map(
                static fn (array $problem): string => $problem['detail'],
                $report->errors,
            )),
        ));
    }
}
