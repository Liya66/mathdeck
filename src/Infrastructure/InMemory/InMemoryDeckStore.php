<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Authoring\DeckStatus;
use MathDeck\Authoring\DeckVersion;
use MathDeck\Authoring\Port\DeckStore;

final class InMemoryDeckStore implements DeckStore
{
    /** @var array<string, DeckVersion> */
    private array $versions = [];

    public static function withStarterDeck(): self
    {
        $store = new self();
        $store->seedStarterDeck();

        return $store;
    }

    /** Mirrors what migration 002 inserts, so tests and a fresh database agree. */
    public function seedStarterDeck(): void
    {
        $this->save(new DeckVersion(
            deckId: 'starter',
            version: 1,
            authorId: 'system',
            status: DeckStatus::Published,
            document: DeckDocument::starter(),
            updatedAt: new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')),
            publishedAt: new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')),
        ));
    }

    public function save(DeckVersion $version): void
    {
        $this->versions[$version->id()] = $version;
    }

    public function find(string $deckVersionId): ?DeckVersion
    {
        return $this->versions[$deckVersionId] ?? null;
    }

    public function all(): array
    {
        $all = array_values($this->versions);

        usort($all, static fn (DeckVersion $a, DeckVersion $b): int => $b->updatedAt <=> $a->updatedAt);

        return $all;
    }

    public function nextVersion(string $deckId): int
    {
        $highest = 0;

        foreach ($this->versions as $version) {
            if ($version->deckId === $deckId) {
                $highest = max($highest, $version->version);
            }
        }

        return $highest + 1;
    }
}
