<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Analytics;

use MathDeck\Analytics\Port\AttemptStore;
use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Infrastructure\InMemory\InMemoryAttemptStore;
use MathDeck\Tests\Support\ReportQueriesContract;

final class InMemoryReportQueriesTest extends ReportQueriesContract
{
    private InMemoryAttemptStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryAttemptStore();

        parent::setUp();
    }

    protected function attemptStore(): AttemptStore
    {
        return $this->store;
    }

    protected function reports(): ReportQueries
    {
        return $this->store;
    }
}
