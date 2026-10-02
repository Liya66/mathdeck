<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Application\Port\DeckCatalog;
use MathDeck\Application\Port\MatchIdentityFactory;
use MathDeck\Engine\Clock;
use MathDeck\Engine\State\MatchState;

final readonly class MatchCreator
{
    public function __construct(
        private MatchRepository $repository,
        private DeckCatalog $decks,
        private MatchIdentityFactory $identity,
        private Clock $clock,
    ) {
    }

    /** @param list<string> $playerIds */
    public function create(string $deckVersionId, array $playerIds): MatchState
    {
        return $this->repository->create(
            matchId: $this->identity->newMatchId(),
            deckVersionId: $deckVersionId,
            // Copied into the match, so a later edit to the deck cannot change how
            // this one replays.
            rules: $this->decks->rulesFor($deckVersionId),
            seed: $this->identity->newSeed(),
            playerIds: $playerIds,
            createdAt: $this->clock->now(),
        );
    }
}
