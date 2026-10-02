<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Analytics\Port\ProjectionCursors;

final readonly class MysqlProjectionCursors implements ProjectionCursors
{
    public function __construct(private \PDO $connection)
    {
    }

    public function positionOf(string $projection, string $streamId): int
    {
        $statement = $this->connection->prepare(
            'SELECT seq FROM projection_cursors WHERE projection = :projection AND stream_id = :stream',
        );
        $statement->execute(['projection' => $projection, 'stream' => $streamId]);

        $seq = $statement->fetchColumn();

        return $seq === false ? 0 : (int) $seq;
    }

    public function advance(string $projection, string $streamId, int $seq): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO projection_cursors (projection, stream_id, seq, updated_at)
             VALUES (:projection, :stream, :seq, :now)
             ON DUPLICATE KEY UPDATE seq = GREATEST(seq, VALUES(seq)), updated_at = VALUES(updated_at)',
        );

        $statement->execute([
            'projection' => $projection,
            'stream' => $streamId,
            'seq' => $seq,
            'now' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
        ]);
    }
}
