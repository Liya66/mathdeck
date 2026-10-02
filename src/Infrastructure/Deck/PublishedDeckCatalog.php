<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Deck;

use MathDeck\Application\Exception\DeckNotFound;
use MathDeck\Application\Port\DeckCatalog;
use MathDeck\Authoring\DeckStatus;
use MathDeck\Authoring\Port\DeckStore;
use MathDeck\Engine\State\DeckRules;

/**
 * Bridges the authoring store to the match side.
 *
 * A draft cannot start a match. Half-finished decks are exactly the ones with
 * unreachable targets, and the publish gate is what stands between a teacher's
 * work-in-progress and a lesson.
 */
final readonly class PublishedDeckCatalog implements DeckCatalog
{
    public function __construct(private DeckStore $decks)
    {
    }

    public function rulesFor(string $deckVersionId): DeckRules
    {
        $version = $this->decks->find($deckVersionId);

        if ($version === null || $version->status !== DeckStatus::Published) {
            throw DeckNotFound::withId($deckVersionId);
        }

        return $version->document->toDeckRules();
    }
}
