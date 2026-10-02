<?php

declare(strict_types=1);

namespace MathDeck\Identity\Exception;

final class AccountNotFound extends \RuntimeException
{
    public static function of(string $playerId): self
    {
        return new self(sprintf('There is no account for "%s".', $playerId));
    }
}
