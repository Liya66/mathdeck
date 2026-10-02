<?php

declare(strict_types=1);

namespace MathDeck\Application\Port;

use MathDeck\Application\Exception\DuplicateCommand;
use MathDeck\Application\Exception\SequenceConflict;
use MathDeck\Application\StoredEvent;
use MathDeck\Engine\Event\Event;

interface EventStore
{
    /**
     * Append events at $fromSeq + 1 onwards, recording the command that produced
     * them in the same transaction.
     *
     * The two must be atomic. If the events landed but the command id did not, a
     * retried request would play the same cards a second time.
     *
     * @param list<Event> $events
     *
     * @throws SequenceConflict if another writer already claimed one of those slots
     * @throws DuplicateCommand if this client command id is already on record
     */
    public function append(string $matchId, int $fromSeq, array $events, string $clientCommandId): void;

    /** @return list<StoredEvent> */
    public function load(string $matchId, int $fromSeq = 0): array;

    /**
     * The events a previously accepted command produced, or null if it is new.
     *
     * @return list<StoredEvent>|null
     */
    public function findByClientCommandId(string $matchId, string $clientCommandId): ?array;
}
