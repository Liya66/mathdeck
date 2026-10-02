<?php

declare(strict_types=1);

namespace MathDeck\Application\Port;

/**
 * Match ids and seeds are minted by the server, never accepted from a client.
 *
 * A client that chooses the seed knows the deck order before the first card is
 * dealt, which is the same as being handed the draw pile. There is deliberately no
 * way to pass one in over HTTP.
 */
interface MatchIdentityFactory
{
    public function newMatchId(): string;

    public function newSeed(): int;
}
