<?php

declare(strict_types=1);

namespace MathDeck\Engine\State;

use MathDeck\Engine\Card\Card;

final readonly class PlayerState
{
    /** @param list<Card> $hand */
    public function __construct(
        public string $id,
        public array $hand,
        public int $score = 0,
    ) {
    }

    /** @param list<string> $cardIds */
    public function holdsAll(array $cardIds): bool
    {
        $held = array_map(static fn (Card $card): string => $card->id, $this->hand);

        foreach ($cardIds as $cardId) {
            $position = array_search($cardId, $held, strict: true);

            if ($position === false) {
                return false;
            }

            unset($held[$position]);
        }

        return true;
    }

    /**
     * @param list<string> $cardIds
     *
     * @return list<Card>
     */
    public function cardsById(array $cardIds): array
    {
        $byId = [];

        foreach ($this->hand as $card) {
            $byId[$card->id] = $card;
        }

        return array_map(
            static fn (string $cardId): Card => $byId[$cardId]
                ?? throw new \LogicException(sprintf('Card %s is not in hand.', $cardId)),
            $cardIds,
        );
    }

    /** @param list<string> $cardIds */
    public function withoutCards(array $cardIds): self
    {
        $remaining = [];
        $toRemove = array_count_values($cardIds);

        foreach ($this->hand as $card) {
            if (($toRemove[$card->id] ?? 0) > 0) {
                --$toRemove[$card->id];

                continue;
            }

            $remaining[] = $card;
        }

        return new self($this->id, $remaining, $this->score);
    }

    /** @param list<Card> $cards */
    public function withCards(array $cards): self
    {
        return new self($this->id, [...$this->hand, ...$cards], $this->score);
    }

    public function withScoreIncreasedBy(int $points): self
    {
        return new self($this->id, $this->hand, $this->score + $points);
    }
}
