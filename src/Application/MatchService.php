<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Application\Exception\ConcurrencyExhausted;
use MathDeck\Application\Exception\DuplicateCommand;
use MathDeck\Application\Exception\SequenceConflict;
use MathDeck\Application\Port\EventStore;
use MathDeck\Engine\Command\Command;
use MathDeck\Engine\Engine;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Exception\IllegalCommand;

/**
 * The first class allowed to touch storage.
 *
 * Load, decide, append, retry: the engine stays pure and this is where the messy
 * parts of the real world — concurrency, retries, duplicate requests — are handled.
 */
final readonly class MatchService
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private Engine $engine,
        private MatchRepository $repository,
        private EventStore $events,
    ) {
    }

    /**
     * @throws IllegalCommand        the command was refused outright; nothing is written
     * @throws ConcurrencyExhausted  too much contention on one match
     */
    public function execute(Command $command): CommandOutcome
    {
        $matchId = $command->matchId();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $alreadyApplied = $this->replayOf($matchId, $command->clientCommandId());

            if ($alreadyApplied !== null) {
                return $alreadyApplied;
            }

            $state = $this->repository->load($matchId);
            $events = $this->engine->handle($state, $command);

            try {
                $this->events->append($matchId, $state->seq, $events, $command->clientCommandId());
            } catch (SequenceConflict | DuplicateCommand) {
                // Somebody else wrote first. Our decision was made against stale
                // state, so throw it away and decide again — never force the write.
                continue;
            }

            return CommandOutcome::applied($events, $state->applyAll($events), $state->seq + 1);
        }

        throw ConcurrencyExhausted::after(self::MAX_ATTEMPTS, $matchId);
    }

    private function replayOf(string $matchId, string $clientCommandId): ?CommandOutcome
    {
        $stored = $this->events->findByClientCommandId($matchId, $clientCommandId);

        if ($stored === null) {
            return null;
        }

        return CommandOutcome::replayed(
            array_map(static fn (StoredEvent $event): Event => $event->event, $stored),
            $this->repository->load($matchId),
            $stored[0]->seq,
        );
    }
}
