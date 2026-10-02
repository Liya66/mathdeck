<?php

declare(strict_types=1);

namespace MathDeck\Engine\State;

use MathDeck\Engine\Card\Card;

/**
 * Shared table state. The draw pile and target queue are generated once from the
 * match seed, so no randomness is ever needed while handling a command.
 */
final readonly class Board
{
    /**
     * @param list<Card> $drawPile
     * @param list<int>  $targetQueue
     */
    public function __construct(
        public int $target,
        public array $drawPile,
        public array $targetQueue,
    ) {
    }

    public function hasNextTarget(): bool
    {
        return $this->targetQueue !== [];
    }

    public function nextTarget(): int
    {
        return $this->targetQueue[0]
            ?? throw new \LogicException('No targets remain.');
    }

    /** @return list<Card> */
    public function peekDraw(int $count): array
    {
        return array_slice($this->drawPile, 0, $count);
    }

    public function withTargetTaken(): self
    {
        return new self(
            target: $this->nextTarget(),
            drawPile: $this->drawPile,
            targetQueue: array_values(array_slice($this->targetQueue, 1)),
        );
    }

    public function withCardsDrawn(int $count): self
    {
        return new self(
            target: $this->target,
            drawPile: array_values(array_slice($this->drawPile, $count)),
            targetQueue: $this->targetQueue,
        );
    }
}
