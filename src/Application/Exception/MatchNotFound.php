<?php

declare(strict_types=1);

namespace MathDeck\Application\Exception;

final class MatchNotFound extends \RuntimeException
{
    public static function withId(string $matchId): self
    {
        return new self(sprintf('No match with id %s.', $matchId));
    }
}
