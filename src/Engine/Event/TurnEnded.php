<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

/**
 * Also the clock for problem-solving latency: the next turn starts at occurredAt,
 * and the following attempt's latency is measured from it. Server-side, always.
 */
final readonly class TurnEnded implements Event
{
    public function __construct(
        private string $matchId,
        private \DateTimeImmutable $occurredAt,
        public string $playerId,
        public int $nextPlayerIndex,
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
        return 'turn_ended';
    }

    public function payload(): array
    {
        return [
            'playerId' => $this->playerId,
            'nextPlayerIndex' => $this->nextPlayerIndex,
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
            $reader->int('nextPlayerIndex'),
        );
    }
}
