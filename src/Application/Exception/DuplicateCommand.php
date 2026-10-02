<?php

declare(strict_types=1);

namespace MathDeck\Application\Exception;

/**
 * This client command id is already recorded against the match. Raised when two
 * copies of the same request race; the caller reloads and returns the original
 * events rather than playing the cards twice.
 */
final class DuplicateCommand extends \RuntimeException
{
    public static function of(string $matchId, string $clientCommandId): self
    {
        return new self(sprintf('Command %s was already applied to match %s.', $clientCommandId, $matchId));
    }
}
