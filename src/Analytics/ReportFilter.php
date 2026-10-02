<?php

declare(strict_types=1);

namespace MathDeck\Analytics;

final readonly class ReportFilter
{
    /** @param list<string> $studentKeys */
    public function __construct(
        public ?string $deckVersionId = null,
        public array $studentKeys = [],
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
    ) {
    }

    public function matches(Attempt $attempt): bool
    {
        if ($this->deckVersionId !== null && $attempt->deckVersionId !== $this->deckVersionId) {
            return false;
        }

        if ($this->studentKeys !== [] && !in_array($attempt->studentKey, $this->studentKeys, strict: true)) {
            return false;
        }

        if ($this->from !== null && $attempt->occurredAt < $this->from) {
            return false;
        }

        return $this->to === null || $attempt->occurredAt <= $this->to;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'deckVersionId' => $this->deckVersionId,
            'studentKeys' => $this->studentKeys,
            'from' => $this->from?->format('Y-m-d\TH:i:s.up'),
            'to' => $this->to?->format('Y-m-d\TH:i:s.up'),
        ];
    }
}
