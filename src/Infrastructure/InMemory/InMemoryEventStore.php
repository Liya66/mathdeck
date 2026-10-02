<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Application\Exception\DuplicateCommand;
use MathDeck\Application\Exception\SequenceConflict;
use MathDeck\Application\Port\EventStore;
use MathDeck\Application\StoredEvent;
use MathDeck\Engine\Event\Event;

/**
 * Reference implementation and test double in one. The unit tests for MatchService
 * run against this; the MySQL store is verified against the same behaviours in the
 * integration suite.
 */
final class InMemoryEventStore implements EventStore
{
    /** @var array<string, list<StoredEvent>> */
    private array $streams = [];

    /** @var array<string, array{from: int, to: int}> */
    private array $commands = [];

    /** Lets a test simulate another writer winning the race. */
    public ?\Closure $beforeAppend = null;

    public function append(string $matchId, int $fromSeq, array $events, string $clientCommandId): void
    {
        ($this->beforeAppend ?? static fn (): null => null)();

        $commandKey = $matchId . '|' . $clientCommandId;

        if (isset($this->commands[$commandKey])) {
            throw DuplicateCommand::of($matchId, $clientCommandId);
        }

        $stream = $this->streams[$matchId] ?? [];

        if (count($stream) !== $fromSeq) {
            throw SequenceConflict::at($matchId, $fromSeq + 1);
        }

        $seq = $fromSeq;

        foreach ($events as $event) {
            $stream[] = new StoredEvent(++$seq, $event);
        }

        $this->streams[$matchId] = $stream;
        $this->commands[$commandKey] = ['from' => $fromSeq + 1, 'to' => $seq];
    }

    public function load(string $matchId, int $fromSeq = 0): array
    {
        return array_values(array_filter(
            $this->streams[$matchId] ?? [],
            static fn (StoredEvent $stored): bool => $stored->seq > $fromSeq,
        ));
    }

    public function findByClientCommandId(string $matchId, string $clientCommandId): ?array
    {
        $range = $this->commands[$matchId . '|' . $clientCommandId] ?? null;

        if ($range === null) {
            return null;
        }

        return array_values(array_filter(
            $this->streams[$matchId] ?? [],
            static fn (StoredEvent $stored): bool => $stored->seq >= $range['from'] && $stored->seq <= $range['to'],
        ));
    }

    /** @return list<Event> */
    public function eventsOf(string $matchId): array
    {
        return array_map(
            static fn (StoredEvent $stored): Event => $stored->event,
            $this->streams[$matchId] ?? [],
        );
    }
}
