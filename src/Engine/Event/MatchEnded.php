<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

final readonly class MatchEnded implements Event
{
    public function __construct(
        private string $matchId,
        private \DateTimeImmutable $occurredAt,
        public ?string $winnerId,
        public string $reason,
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
        return 'match_ended';
    }

    public function payload(): array
    {
        return [
            'winnerId' => $this->winnerId,
            'reason' => $this->reason,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(string $matchId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $reader = new Payload($payload);

        return new self(
            $matchId,
            $occurredAt,
            $reader->nullableString('winnerId'),
            $reader->string('reason'),
        );
    }
}
