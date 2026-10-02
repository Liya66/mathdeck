<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Analytics;

use MathDeck\Analytics\Port\PseudonymResolver;
use MathDeck\Identity\Port\AccountStore;

final class AccountPseudonymResolver implements PseudonymResolver
{
    /** @var array<string, string|null> */
    private array $cache = [];

    public function __construct(private readonly AccountStore $accounts)
    {
    }

    public function keyFor(string $playerId): ?string
    {
        // A projection run sweeps the same handful of players over and over.
        return $this->cache[$playerId] ??= $this->accounts->analyticsKeyFor($playerId);
    }
}
