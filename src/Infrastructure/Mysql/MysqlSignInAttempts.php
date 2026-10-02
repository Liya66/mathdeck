<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Identity\Port\SignInAttempts;

final readonly class MysqlSignInAttempts implements SignInAttempts
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private \PDO $connection)
    {
    }

    public function failuresSince(string $key, \DateTimeImmutable $since): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM sign_in_attempts WHERE bucket_key = :key AND failed_at >= :since',
        );
        $statement->execute(['key' => $key, 'since' => $since->format(self::TIMESTAMP_FORMAT)]);

        return (int) $statement->fetchColumn();
    }

    public function recordFailure(string $key, \DateTimeImmutable $at): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO sign_in_attempts (bucket_key, failed_at) VALUES (:key, :at)',
        );
        $statement->execute(['key' => $key, 'at' => $at->format(self::TIMESTAMP_FORMAT)]);

        // Rows outside any window are of no interest to anyone; sweeping here keeps
        // the table from growing without bound and needs no scheduled job.
        $this->connection->prepare('DELETE FROM sign_in_attempts WHERE failed_at < :cutoff')
            ->execute(['cutoff' => $at->modify('-1 day')->format(self::TIMESTAMP_FORMAT)]);
    }

    public function clear(string $key): void
    {
        $statement = $this->connection->prepare('DELETE FROM sign_in_attempts WHERE bucket_key = :key');
        $statement->execute(['key' => $key]);
    }
}
