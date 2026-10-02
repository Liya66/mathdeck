<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use MathDeck\Engine\Clock;

final class FrozenClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $at = '2026-01-15 09:00:00.000000')
    {
        $this->now = new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceMs(int $milliseconds): void
    {
        $this->now = $this->now->modify(sprintf('+%d milliseconds', $milliseconds));
    }
}
