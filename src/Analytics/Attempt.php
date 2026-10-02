<?php

declare(strict_types=1);

namespace MathDeck\Analytics;

use MathDeck\Engine\Rule\RejectReason;

/**
 * One try at a target, right or wrong.
 *
 * This is the grain everything a teacher sees is built from. Note what it is *not*:
 * a record of successes. Effort is the thing a dashboard can show that a mark book
 * cannot, so a rejected attempt is as much a row as a solved one.
 *
 * And note whose attempt it is not: `studentKey` is a pseudonym, never a name. The
 * mapping back lives in the identity store behind an authorisation check.
 */
final readonly class Attempt
{
    public function __construct(
        public string $matchId,
        public int $seq,
        public string $studentKey,
        public string $deckVersionId,
        public int $target,
        public ?string $expression,
        public int $cardCount,
        public bool $solved,
        public ?RejectReason $reason,
        public int $score,
        public int $latencyMs,
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public function id(): string
    {
        return $this->matchId . '#' . $this->seq;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'matchId' => $this->matchId,
            'seq' => $this->seq,
            'studentKey' => $this->studentKey,
            'deckVersionId' => $this->deckVersionId,
            'target' => $this->target,
            'expression' => $this->expression,
            'cardCount' => $this->cardCount,
            'solved' => $this->solved,
            'reason' => $this->reason?->value,
            'score' => $this->score,
            'latencyMs' => $this->latencyMs,
            'occurredAt' => $this->occurredAt->format('Y-m-d\TH:i:s.up'),
        ];
    }
}
