<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Application\Exception\DuplicateMatchCreation;
use MathDeck\Application\MatchRecord;
use MathDeck\Application\Port\MatchStore;

final class InMemoryMatchStore implements MatchStore
{
    /** @var array<string, MatchRecord> */
    private array $records = [];

    public function save(MatchRecord $record): void
    {
        if ($record->creationKey !== null) {
            $existing = $this->findByCreationKey($record->creationKey);

            if ($existing !== null && $existing->matchId !== $record->matchId) {
                throw DuplicateMatchCreation::of($record->creationKey);
            }
        }

        $this->records[$record->matchId] = $record;
    }

    public function findByCreationKey(string $creationKey): ?MatchRecord
    {
        foreach ($this->records as $record) {
            if ($record->creationKey === $creationKey) {
                return $record;
            }
        }

        return null;
    }

    public function find(string $matchId): ?MatchRecord
    {
        return $this->records[$matchId] ?? null;
    }

    public function allMatchIds(): array
    {
        return array_keys($this->records);
    }
}
