<?php

declare(strict_types=1);

namespace MathDeck\Tests\Integration;

use MathDeck\Analytics\Port\AttemptStore;
use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Infrastructure\Mysql\Connection;
use MathDeck\Infrastructure\Mysql\MysqlAttemptStore;
use MathDeck\Infrastructure\Mysql\MysqlReportQueries;
use MathDeck\Tests\Support\ReportQueriesContract;

/**
 * The same expectations as the in-memory implementation, answered by SQL.
 */
final class MysqlReportQueriesTest extends ReportQueriesContract
{
    private \PDO $connection;

    protected function setUp(): void
    {
        $connection = Connection::fromEnvironment();

        if ($connection === null) {
            self::markTestSkipped('MATCHDECK_DSN is not set; run `make test-integration`.');
        }

        $this->connection = $connection;
        $this->connection->exec('DELETE FROM fact_attempt');

        parent::setUp();
    }

    protected function attemptStore(): AttemptStore
    {
        return new MysqlAttemptStore($this->connection);
    }

    protected function reports(): ReportQueries
    {
        return new MysqlReportQueries($this->connection);
    }
}
