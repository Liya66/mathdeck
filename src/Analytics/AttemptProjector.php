<?php

declare(strict_types=1);

namespace MathDeck\Analytics;

use MathDeck\Analytics\Port\PseudonymResolver;
use MathDeck\Application\StoredEvent;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;

/**
 * Turns the match log into attempt facts.
 *
 * Every attempt is two events: a `cards_played` carrying the expression and the
 * server-measured latency, then its outcome. The projector pairs them and stops at
 * the last completed pair — a trailing `cards_played` means the batch was cut mid
 * attempt, and half an attempt is not a fact.
 */
final readonly class AttemptProjector
{
    public function __construct(private PseudonymResolver $pseudonyms)
    {
    }

    /**
     * @param list<StoredEvent> $events
     *
     * @return array{attempts: list<Attempt>, cursor: int, skipped: int}
     */
    public function project(array $events, string $deckVersionId, int $cursor): array
    {
        $attempts = [];
        $skipped = 0;
        $pending = null;
        $safeCursor = $cursor;

        foreach ($events as $stored) {
            $event = $stored->event;

            if ($event instanceof CardsPlayed) {
                $pending = $stored;

                continue;
            }

            if (!$event instanceof EquationSolved && !$event instanceof EquationRejected) {
                // Events that produce no fact still move the cursor, as long as we
                // are not in the middle of an attempt.
                if ($pending === null) {
                    $safeCursor = $stored->seq;
                }

                continue;
            }

            if (!$pending instanceof StoredEvent || !$pending->event instanceof CardsPlayed) {
                continue; // An outcome with no attempt: not something to invent a row for.
            }

            $played = $pending->event;
            $pending = null;
            $studentKey = $this->pseudonyms->keyFor($played->playerId);

            if ($studentKey === null) {
                // No account to pseudonymise against — a deleted one, most likely.
                // Skipping is the right answer for the same reason the column
                // exists: without a key there is nothing to store this under that
                // is not a name. The cursor still moves, so a deleted account does
                // not stall the projection forever.
                ++$skipped;
                $safeCursor = $stored->seq;

                continue;
            }

            $attempts[] = new Attempt(
                matchId: $played->matchId(),
                seq: $stored->seq,
                studentKey: $studentKey,
                deckVersionId: $deckVersionId,
                target: $played->target,
                expression: $played->expression,
                cardCount: count($played->cardIds),
                solved: $event instanceof EquationSolved,
                reason: $event instanceof EquationRejected ? $event->reason : null,
                score: $event instanceof EquationSolved ? $event->score : 0,
                latencyMs: $played->latencyMs,
                occurredAt: $played->occurredAt(),
            );

            $safeCursor = $stored->seq;
        }

        return ['attempts' => $attempts, 'cursor' => $safeCursor, 'skipped' => $skipped];
    }
}
