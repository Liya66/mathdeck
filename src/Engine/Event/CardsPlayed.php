<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

/**
 * An attempt was made. Emitted whether or not the attempt was correct, which is
 * what makes latency and attempt counts measurable.
 */
final readonly class CardsPlayed implements Event
{
    /** @param list<string> $cardIds */
    public function __construct(
        private string $matchId,
        private \DateTimeImmutable $occurredAt,
        public string $playerId,
        public array $cardIds,
        public ?string $expression,
        public int $target,
        public int $latencyMs,
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
        return 'cards_played';
    }

    public function payload(): array
    {
        return [
            'playerId' => $this->playerId,
            'cardIds' => $this->cardIds,
            'expression' => $this->expression,
            'target' => $this->target,
            'latencyMs' => $this->latencyMs,
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
            $reader->int('latencyMs'),
        );
    }
}
