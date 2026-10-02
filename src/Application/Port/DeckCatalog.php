<?php

declare(strict_types=1);

namespace MathDeck\Application\Port;

use MathDeck\Application\Exception\DeckNotFound;
use MathDeck\Engine\State\DeckRules;

/**
 * Resolves a published deck version to the rules a match should be created with.
 *
 * Phase 6 replaces the implementation with the authoring store; the port exists now
 * so the HTTP layer never learns where decks come from.
 */
interface DeckCatalog
{
    /** @throws DeckNotFound */
    public function rulesFor(string $deckVersionId): DeckRules;
}
