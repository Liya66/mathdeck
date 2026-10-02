<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Engine\State\DeckRules;

/**
 * Everything needed to recreate a match's opening position.
 *
 * Because MatchState::start() is deterministic, this record plus the event log is
 * the entire match — there is no serialised game state anywhere, and therefore no
 * state schema to version, migrate or get subtly wrong. Snapshots, when they are
 * eventually needed, are a cache over this and nothing more.
 */
final readonly class MatchRecord
{
    /** @param list<string> $playerIds */
    public function __construct(
        public string $matchId,
        public string $deckVersionId,
        public int $seed,
        public array $playerIds,
        public DeckRules $rules,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
