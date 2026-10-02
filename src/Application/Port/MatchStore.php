<?php

declare(strict_types=1);

namespace MathDeck\Application\Port;

use MathDeck\Application\MatchRecord;

interface MatchStore
{
    public function save(MatchRecord $record): void;

    public function find(string $matchId): ?MatchRecord;

    /**
     * Every match id, for the projection worker to sweep. Classroom scale; a
     * school-wide deployment would want this to take a cursor of its own.
     *
     * @return list<string>
     */
    public function allMatchIds(): array;
}
