<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Analytics\Port\ProjectionCursors;

final class InMemoryProjectionCursors implements ProjectionCursors
{
    /** @var array<string, int> */
    private array $positions = [];

    public function positionOf(string $projection, string $streamId): int
    {
        return $this->positions[$projection . '|' . $streamId] ?? 0;
    }

    public function advance(string $projection, string $streamId, int $seq): void
    {
        $this->positions[$projection . '|' . $streamId] = $seq;
    }
}
