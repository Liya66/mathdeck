<?php

declare(strict_types=1);

namespace MathDeck\Analytics;

use MathDeck\Analytics\Port\AttemptStore;
use MathDeck\Analytics\Port\ProjectionCursors;
use MathDeck\Application\Port\EventStore;
use MathDeck\Application\Port\MatchStore;

/**
 * Drives the projection: read what is new, project it, record how far we got.
 *
 * Runs outside the request path. A command that had to wait for analytics before
 * answering would make the game slower every time a report got more expensive.
 */
final readonly class ProjectionRunner
{
    public const PROJECTION = 'attempts';

    public function __construct(
        private MatchStore $matches,
        private EventStore $events,
        private AttemptStore $attempts,
        private ProjectionCursors $cursors,
        private AttemptProjector $projector,
    ) {
    }

    /** @return array{matches: int, attempts: int, skipped: int} */
    public function run(): array
    {
        $projected = 0;
        $touched = 0;
        $skipped = 0;

        foreach ($this->matches->allMatchIds() as $matchId) {
            $record = $this->matches->find($matchId);

            if ($record === null) {
                continue;
            }

            $cursor = $this->cursors->positionOf(self::PROJECTION, $matchId);
            $events = $this->events->load($matchId, $cursor);

            if ($events === []) {
                continue;
            }

            $result = $this->projector->project($events, $record->deckVersionId, $cursor);

            $skipped += $result['skipped'];

            if ($result['attempts'] !== []) {
                $this->attempts->append($result['attempts']);
                $projected += count($result['attempts']);
            }

            if ($result['cursor'] > $cursor) {
                $this->cursors->advance(self::PROJECTION, $matchId, $result['cursor']);
                ++$touched;
            }
        }

        return ['matches' => $touched, 'attempts' => $projected, 'skipped' => $skipped];
    }
}
