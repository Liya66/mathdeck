<?php

declare(strict_types=1);

namespace MathDeck\Analytics\Port;

/**
 * Turns a player id into the key analytics stores them under.
 *
 * The projection is the boundary where names stop. Everything downstream of here —
 * the fact table, the report queries, the dashboard's filters — deals only in
 * pseudonyms, and the route back is an authorised lookup in the identity store.
 */
interface PseudonymResolver
{
    /** Null when there is no account, e.g. one that has since been deleted. */
    public function keyFor(string $playerId): ?string;
}
