<?php

declare(strict_types=1);

namespace MathDeck\Identity;

use MathDeck\Engine\Clock;

final readonly class AccountFactory
{
    public function __construct(
        private PasswordHasher $hasher,
        private Clock $clock,
    ) {
    }

    public function create(string $playerId, string $displayName, Role $role, string $passcode): Account
    {
        return new Account(
            playerId: $playerId,
            displayName: $displayName,
            role: $role,
            passwordHash: $this->hasher->hash($passcode),
            // Random, not derived from the player id: a derived key can be recomputed
            // by anyone who guesses the scheme, which makes it a label rather than a
            // pseudonym.
            analyticsKey: bin2hex(random_bytes(16)),
            createdAt: $this->clock->now(),
        );
    }
}
