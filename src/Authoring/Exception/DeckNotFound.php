<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Exception;

final class DeckNotFound extends \RuntimeException
{
    public static function withId(string $deckVersionId): self
    {
        return new self(sprintf('No deck version %s.', $deckVersionId));
    }
}
