<?php

declare(strict_types=1);

namespace MathDeck\Application\Exception;

/**
 * This creation key already made a match. Raised when two copies of the same
 * request race; the caller reads back the match the winner created.
 */
final class DuplicateMatchCreation extends \RuntimeException
{
    public static function of(string $creationKey): self
    {
        return new self(sprintf('A match was already created with key %s.', $creationKey));
    }
}
