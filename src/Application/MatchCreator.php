<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Application\Exception\DuplicateMatchCreation;
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

    /**
     * Idempotent on the creation key.
     *
     * Commands have carried an idempotency key since phase 4; creation was the one
     * write that did not — and it is the request most likely to be retried, because
     * a child who taps "new match" and sees nothing taps it again.
     *
     * @param list<string> $playerIds
     */
    public function create(string $deckVersionId, array $playerIds, string $creationKey): MatchState
    {
        $existing = $this->repository->findByCreationKey($creationKey);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return $this->createNew($deckVersionId, $playerIds, $creationKey);
        } catch (DuplicateMatchCreation) {
            // Another copy of the same request won the race between the check above
            // and the insert. Read back what it made.
            return $this->repository->findByCreationKey($creationKey)
                ?? throw new \RuntimeException('Lost a creation race to a match that then vanished.');
        }
    }

    /** @param list<string> $playerIds */
    private function createNew(string $deckVersionId, array $playerIds, string $creationKey): MatchState
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
            creationKey: $creationKey,
        );
    }
}
