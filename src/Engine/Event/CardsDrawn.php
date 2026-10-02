<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

use MathDeck\Engine\Card\Card;

final readonly class CardsDrawn implements Event
{
    /** @param list<Card> $cards */
    public function __construct(
        private string $matchId,
        private \DateTimeImmutable $occurredAt,
        public string $playerId,
        public array $cards,
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
        return 'cards_drawn';
    }

    public function payload(): array
    {
        return [
            'playerId' => $this->playerId,
            'cards' => array_map(static fn (Card $card): array => $card->toArray(), $this->cards),
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
            array_map(
                static fn (array $card): Card => Card::fromArray($card),
                $reader->mapList('cards'),
            ),
        );
    }
}
