<?php

declare(strict_types=1);

namespace MathDeck\Tests\Integration;

use MathDeck\Infrastructure\Mysql\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests run against a real MySQL — an in-memory fake cannot tell you
 * whether a unique index actually stops a concurrent writer.
 *
 * They skip themselves when MATCHDECK_DSN is unset or unreachable, so `make test`
 * stays fast and dependency-free. `make test-integration` brings the database up.
 */
abstract class MysqlTestCase extends TestCase
{
    protected \PDO $connection;

    protected function setUp(): void
    {
        // No DSN means nobody asked for integration tests, so skip. But a DSN that
        // is set and does not work is a failure, not a skip: silently skipping is
        // how a CI job goes green without ever touching a database.
        $connection = Connection::fromEnvironment();

        if ($connection === null) {
            self::markTestSkipped('MATCHDECK_DSN is not set; run `make test-integration`.');
        }

        $this->connection = $connection;

        // Children first: the event and command tables reference matches.
        $this->connection->exec('DELETE FROM match_events');
        $this->connection->exec('DELETE FROM match_commands');
        $this->connection->exec('DELETE FROM matches');
    }
}
