<?php

declare(strict_types=1);

namespace MathDeck\Authoring;

enum DeckStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
