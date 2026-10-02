<?php

declare(strict_types=1);

namespace MathDeck\Engine\Command;

final readonly class PlayCards implements Command
{
    /** @param list<string> $cardIds Ordered left to right as the player laid them out. */
    public function __construct(
        private string $matchId,
        private string $playerId,
        public array $cardIds,
        private string $clientCommandId,
    ) {
    }

    public function matchId(): string
    {
        return $this->matchId;
    }

    public function playerId(): string
    {
        return $this->playerId;
    }

    public function clientCommandId(): string
    {
        return $this->clientCommandId;
    }
}
