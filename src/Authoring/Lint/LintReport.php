<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Lint;

/**
 * What the editor shows a teacher, and what the publish endpoint decides on.
 *
 * Errors block publication; warnings do not. The distinction matters: a deck with a
 * target that is merely hard is the teacher's business, but a deck with a target
 * nobody can ever reach would silently waste a lesson.
 */
final readonly class LintReport
{
    /**
     * @param list<array{code: string, path: string, detail: string}> $errors
     * @param list<array{code: string, path: string, detail: string}> $warnings
     * @param list<TargetReport>                                      $targets
     */
    public function __construct(
        public array $errors,
        public array $warnings,
        public array $targets = [],
    ) {
    }

    /** @param list<array{code: string, path: string, detail: string}> $errors */
    public static function schemaFailure(array $errors): self
    {
        return new self($errors, []);
    }

    public function isPublishable(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'publishable' => $this->isPublishable(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'targets' => array_map(static fn (TargetReport $t): array => $t->toArray(), $this->targets),
        ];
    }
}
