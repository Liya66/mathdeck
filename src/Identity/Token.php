<?php

declare(strict_types=1);

namespace MathDeck\Identity;

final readonly class Token
{
    public function __construct(
        public string $value,
        public string $playerId,
        public Role $role,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
