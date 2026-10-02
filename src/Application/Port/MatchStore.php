<?php

declare(strict_types=1);

namespace MathDeck\Application\Port;

use MathDeck\Application\MatchRecord;

interface MatchStore
{
    public function save(MatchRecord $record): void;

    /** @throws \MathDeck\Application\Exception\DuplicateMatchCreation */
    public function find(string $matchId): ?MatchRecord;

    public function findByCreationKey(string $creationKey): ?MatchRecord;

    /**
     * Every match id, for the projection worker to sweep. Classroom scale; a
     * school-wide deployment would want this to take a cursor of its own.
     *
     * @return list<string>
     */
    public function allMatchIds(): array;
}
