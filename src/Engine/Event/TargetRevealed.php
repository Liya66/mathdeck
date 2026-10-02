<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

final readonly class TargetRevealed implements Event
{
    public function __construct(
        private string $matchId,
        private \DateTimeImmutable $occurredAt,
        public int $target,
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
        return 'target_revealed';
    }

    public function payload(): array
    {
        return ['target' => $this->target];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(string $matchId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        return new self($matchId, $occurredAt, (new Payload($payload))->int('target'));
    }
}
