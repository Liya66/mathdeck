<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Application\MatchRecord;
use MathDeck\Application\Port\MatchStore;

final class InMemoryMatchStore implements MatchStore
{
    /** @var array<string, MatchRecord> */
    private array $records = [];

    public function save(MatchRecord $record): void
    {
        $this->records[$record->matchId] = $record;
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
