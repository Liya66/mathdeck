<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Application\Exception\PlayerNotInMatch;
use MathDeck\Application\Port\EventStore;

/**
 * Reads a match's log on behalf of one player.
 *
 * Authorisation happens against the opening position rather than the replayed
 * state: who is in a match is fixed at creation, so there is no need to fold the
 * whole log just to find out whether the caller belongs here.
 */
final readonly class MatchEventReader
{
    public function __construct(
        private MatchRepository $repository,
        private EventStore $events,
    ) {
    }

    /**
     * @throws PlayerNotInMatch
     *
     * @return list<StoredEvent>
     */
    public function since(string $matchId, string $playerId, int $seq): array
    {
        $opening = $this->repository->openingPosition($matchId);

        if ($opening->playerById($playerId) === null) {
            throw PlayerNotInMatch::of($playerId, $matchId);
        }

        return $this->events->load($matchId, max(0, $seq));
    }
}
