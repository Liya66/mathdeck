<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Exception;

use MathDeck\Authoring\DeckStatus;

/**
 * A published deck is immutable.
 *
 * Every match stores the deck version it was created with, and every analytics
 * comparison is between versions. Letting a teacher edit a published deck mid-term
 * would rewrite the meaning of results already collected — silently, and with no
 * way to tell afterwards. Edits fork a new draft instead.
 */
final class DeckNotEditable extends \RuntimeException
{
    public static function because(string $deckVersionId, DeckStatus $status): self
    {
        return new self(sprintf(
            'Deck version %s is %s and cannot be edited. Fork it into a new draft instead.',
            $deckVersionId,
            $status->value,
        ));
    }
}
