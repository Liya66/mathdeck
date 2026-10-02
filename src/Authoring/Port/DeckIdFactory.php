<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Port;

interface DeckIdFactory
{
    public function newDeckId(string $name): string;
}
