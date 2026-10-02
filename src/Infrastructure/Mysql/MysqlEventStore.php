<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Application\EventSerializer;
use MathDeck\Application\Exception\DuplicateCommand;
use MathDeck\Application\Exception\SequenceConflict;
use MathDeck\Application\Port\EventStore;
use MathDeck\Application\StoredEvent;

final readonly class MysqlEventStore implements EventStore
{
    private const DUPLICATE_KEY = '23000';
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(
        private \PDO $connection,
        private EventSerializer $serializer,
    ) {
    }

    /**
     * The command row goes in first, on purpose.
     *
     * Both inserts can fail with the same duplicate-key SQLSTATE, and parsing index
     * names out of driver messages is brittle. Ordering the writes so that only one
     * constraint can be in play at a time makes the failure unambiguous without
     * reading the error text at all.
     */
    public function append(string $matchId, int $fromSeq, array $events, string $clientCommandId): void
    {
        if ($events === []) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            $this->recordCommand($matchId, $clientCommandId, $fromSeq + 1, $fromSeq + count($events));
            $this->insertEvents($matchId, $fromSeq, $events);

            $this->connection->commit();
        } catch (\Throwable $failure) {
            $this->connection->rollBack();

            throw $failure;
        }
    }

    public function load(string $matchId, int $fromSeq = 0): array
    {
        $statement = $this->connection->prepare(
            'SELECT seq, type, payload, occurred_at
             FROM match_events
             WHERE match_id = :match_id AND seq > :from_seq
             ORDER BY seq ASC',
        );
        $statement->execute(['match_id' => $matchId, 'from_seq' => $fromSeq]);

        return $this->hydrate($matchId, $statement);
    }

    public function findByClientCommandId(string $matchId, string $clientCommandId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT from_seq, to_seq FROM match_commands
             WHERE match_id = :match_id AND client_command_id = :client_command_id',
        );
        $statement->execute(['match_id' => $matchId, 'client_command_id' => $clientCommandId]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $range = $this->connection->prepare(
            'SELECT seq, type, payload, occurred_at
             FROM match_events
             WHERE match_id = :match_id AND seq BETWEEN :from_seq AND :to_seq
             ORDER BY seq ASC',
        );
        $range->execute([
            'match_id' => $matchId,
            'from_seq' => (int) $row['from_seq'],
            'to_seq' => (int) $row['to_seq'],
        ]);

        return $this->hydrate($matchId, $range);
    }

    private function recordCommand(string $matchId, string $clientCommandId, int $fromSeq, int $toSeq): void
    {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO match_commands (match_id, client_command_id, from_seq, to_seq, recorded_at)
                 VALUES (:match_id, :client_command_id, :from_seq, :to_seq, :recorded_at)',
            );
            $statement->execute([
                'match_id' => $matchId,
                'client_command_id' => $clientCommandId,
                'from_seq' => $fromSeq,
                'to_seq' => $toSeq,
                'recorded_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    ->format(self::TIMESTAMP_FORMAT),
            ]);
        } catch (\PDOException $failure) {
            if ($failure->getCode() === self::DUPLICATE_KEY) {
                throw DuplicateCommand::of($matchId, $clientCommandId);
            }

            throw $failure;
        }
    }

    /** @param list<\MathDeck\Engine\Event\Event> $events */
    private function insertEvents(string $matchId, int $fromSeq, array $events): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO match_events (match_id, seq, type, payload, occurred_at)
             VALUES (:match_id, :seq, :type, :payload, :occurred_at)',
        );

        $seq = $fromSeq;

        foreach ($events as $event) {
            ++$seq;

            try {
                $statement->execute([
                    'match_id' => $matchId,
                    'seq' => $seq,
                    'type' => $event->type(),
                    'payload' => $this->serializer->encode($event),
                    'occurred_at' => $event->occurredAt()->format(self::TIMESTAMP_FORMAT),
                ]);
            } catch (\PDOException $failure) {
                if ($failure->getCode() === self::DUPLICATE_KEY) {
                    throw SequenceConflict::at($matchId, $seq);
                }

                throw $failure;
            }
        }
    }

    /** @return list<StoredEvent> */
    private function hydrate(string $matchId, \PDOStatement $statement): array
    {
        $stored = [];

        /** @var array<string, mixed> $row */
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $stored[] = new StoredEvent(
                (int) $row['seq'],
                $this->serializer->decode(
                    (string) $row['type'],
                    $matchId,
                    new \DateTimeImmutable((string) $row['occurred_at'], new \DateTimeZone('UTC')),
                    (string) $row['payload'],
                ),
            );
        }

        return $stored;
    }
}
