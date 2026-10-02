<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Random;

use MathDeck\Application\Port\MatchIdentityFactory;

/**
 * The one place in the system that uses real randomness. Everything downstream of
 * the seed is deterministic, which is why this sits at the edge and nowhere else.
 */
final readonly class RandomMatchIdentityFactory implements MatchIdentityFactory
{
    private const MAX_SEED = 2147483647;

    public function newMatchId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function newSeed(): int
    {
        return random_int(0, self::MAX_SEED);
    }
}
