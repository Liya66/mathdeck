<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Application\Exception\MatchNotFound;
use MathDeck\Application\Port\EventStore;
use MathDeck\Application\Port\MatchStore;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Engine\State\MatchState;

/**
 * Rebuilds a match from its seed record and its log. Nothing else is stored.
 */
final readonly class MatchRepository
{
    public function __construct(
        private MatchStore $matches,
        private EventStore $events,
    ) {
    }

    /** @param list<string> $playerIds */
    public function create(
        string $matchId,
        string $deckVersionId,
        int $seed,
        DeckRules $rules,
        array $playerIds,
        \DateTimeImmutable $createdAt,
    ): MatchState {
        $this->matches->save(new MatchRecord(
            matchId: $matchId,
            deckVersionId: $deckVersionId,
            seed: $seed,
            playerIds: $playerIds,
            rules: $rules,
            createdAt: $createdAt,
        ));

        return $this->openingPosition($matchId);
    }

    public function load(string $matchId): MatchState
    {
        return $this->openingPosition($matchId)->applyAll(
            array_map(
                static fn (StoredEvent $stored): \MathDeck\Engine\Event\Event => $stored->event,
                $this->events->load($matchId),
            ),
        );
    }

    /**
     * The deal, before any play. Deterministic from the seed, which is why no game
     * state is ever written to disk.
     */
    public function openingPosition(string $matchId): MatchState
    {
        $record = $this->matches->find($matchId) ?? throw MatchNotFound::withId($matchId);

        return MatchState::start(
            matchId: $record->matchId,
            deckVersionId: $record->deckVersionId,
            seed: $record->seed,
            rules: $record->rules,
            playerIds: $record->playerIds,
            startedAt: $record->createdAt,
        );
    }
}
