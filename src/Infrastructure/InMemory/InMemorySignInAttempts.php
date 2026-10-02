<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Identity\Port\SignInAttempts;

final class InMemorySignInAttempts implements SignInAttempts
{
    /** @var array<string, list<\DateTimeImmutable>> */
    private array $failures = [];

    public function failuresSince(string $key, \DateTimeImmutable $since): int
    {
        return count(array_filter(
            $this->failures[$key] ?? [],
            static fn (\DateTimeImmutable $at): bool => $at >= $since,
        ));
    }

    public function recordFailure(string $key, \DateTimeImmutable $at): void
    {
        $this->failures[$key][] = $at;
    }

    public function clear(string $key): void
    {
        unset($this->failures[$key]);
    }
}
