<?php

declare(strict_types=1);

namespace MathDeck\Application\Exception;

final class ConcurrencyExhausted extends \RuntimeException
{
    public static function after(int $attempts, string $matchId): self
    {
        return new self(sprintf('Gave up on match %s after %d contended attempts.', $matchId, $attempts));
    }
}
