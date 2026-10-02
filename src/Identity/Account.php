<?php

declare(strict_types=1);

namespace MathDeck\Identity;

/**
 * A person who can sign in.
 *
 * `analyticsKey` is deliberately not the player id. Everything the analytics side
 * stores is keyed by it, so the fact table holds no names — the mapping back lives
 * here, behind an authorisation check, and can be dropped without destroying the
 * aggregate picture.
 */
final readonly class Account
{
    public function __construct(
        public string $playerId,
        public string $displayName,
        public Role $role,
        public string $passwordHash,
        public string $analyticsKey,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
