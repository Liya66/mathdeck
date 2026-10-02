<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use MathDeck\Application\Port\MatchIdentityFactory;

final class FixedMatchIdentityFactory implements MatchIdentityFactory
{
    private int $matches = 0;

    public function __construct(private readonly int $seed = 20260115)
    {
    }

    public function newMatchId(): string
    {
        return sprintf('match-%d', ++$this->matches);
    }

    public function newSeed(): int
    {
        return $this->seed;
    }
}
