<?php

declare(strict_types=1);

namespace MathDeck\Engine\Command;

final readonly class Forfeit implements Command
{
    public function __construct(
        private string $matchId,
        private string $playerId,
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
