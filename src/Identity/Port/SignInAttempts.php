<?php

declare(strict_types=1);

namespace MathDeck\Identity\Port;

/**
 * A count of recent failed sign-ins per key, within a sliding window.
 *
 * Deliberately counts *failures only*. Counting every attempt would let a busy
 * classroom lock itself out by signing in normally.
 */
interface SignInAttempts
{
    public function failuresSince(string $key, \DateTimeImmutable $since): int;

    public function recordFailure(string $key, \DateTimeImmutable $at): void;

    /** A correct passcode clears the slate for that key. */
    public function clear(string $key): void;
}
