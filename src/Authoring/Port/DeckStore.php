<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Port;

use MathDeck\Authoring\DeckVersion;

interface DeckStore
{
    public function save(DeckVersion $version): void;

    public function find(string $deckVersionId): ?DeckVersion;

    /**
     * Every version, newest first. Fine at classroom scale; a school-wide catalogue
     * would want paging and a filter pushed into the query.
     *
     * @return list<DeckVersion>
     */
    public function all(): array;

    public function nextVersion(string $deckId): int;
}
