<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use MathDeck\Authoring\Port\DeckIdFactory;

final class FixedDeckIdFactory implements DeckIdFactory
{
    private int $issued = 0;

    public function newDeckId(string $name): string
    {
        return sprintf('deck-%d', ++$this->issued);
    }
}
