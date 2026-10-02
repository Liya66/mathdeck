<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Deck;

use MathDeck\Authoring\Port\DeckIdFactory;

/**
 * Readable ids, because they end up in analytics reports a teacher has to read.
 * The random suffix stops two decks with the same name becoming versions of each
 * other.
 */
final readonly class SlugDeckIdFactory implements DeckIdFactory
{
    public function newDeckId(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));

        if ($slug === '') {
            $slug = 'deck';
        }

        return substr($slug, 0, 40) . '-' . bin2hex(random_bytes(3));
    }
}
