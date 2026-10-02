<?php

declare(strict_types=1);

namespace MathDeck\Analytics\Port;

interface ProjectionCursors
{
    public function positionOf(string $projection, string $streamId): int;

    public function advance(string $projection, string $streamId, int $seq): void;
}
