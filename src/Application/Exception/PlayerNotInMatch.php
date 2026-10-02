<?php

declare(strict_types=1);

namespace MathDeck\Application\Exception;

final class PlayerNotInMatch extends \RuntimeException
{
    public static function of(string $playerId, string $matchId): self
    {
        return new self(sprintf('%s is not a player in match %s.', $playerId, $matchId));
    }
}
