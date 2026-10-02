<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

use MathDeck\Engine\Rule\RejectReason;

/**
 * The attempt did not stand. Cards stay in hand; the turn is what it costs.
 */
final readonly class EquationRejected implements Event
{
    /** @param list<string> $cardIds */
    public function __construct(
        private string $matchId,
        private \DateTimeImmutable $occurredAt,
        public string $playerId,
        public array $cardIds,
        public ?string $expression,
        public int $target,
        public RejectReason $reason,
        public ?string $observedResult,
    ) {
    }

    public function matchId(): string
    {
        return $this->matchId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function type(): string
    {
        return 'equation_rejected';
    }

    public function payload(): array
    {
        return [
            'playerId' => $this->playerId,
            'cardIds' => $this->cardIds,
            'expression' => $this->expression,
            'target' => $this->target,
            'reason' => $this->reason->value,
            'observedResult' => $this->observedResult,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(string $matchId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $reader = new Payload($payload);

        return new self(
            $matchId,
            $occurredAt,
            $reader->string('playerId'),
            $reader->stringList('cardIds'),
            $reader->nullableString('expression'),
            $reader->int('target'),
            RejectReason::from($reader->string('reason')),
            $reader->nullableString('observedResult'),
        );
    }
}
