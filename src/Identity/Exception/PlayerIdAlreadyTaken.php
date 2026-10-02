<?php

declare(strict_types=1);

namespace MathDeck\Identity\Exception;

final class PlayerIdAlreadyTaken extends \RuntimeException
{
    public static function of(string $playerId): self
    {
        return new self(sprintf('Somebody already signs in as "%s".', $playerId));
    }
}
