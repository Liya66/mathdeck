<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use MathDeck\Analytics\Port\PseudonymResolver;

final readonly class StubPseudonymResolver implements PseudonymResolver
{
    /** @param list<string> $withoutAccounts players whose account has gone */
    public function __construct(private array $withoutAccounts = [])
    {
    }

    public function keyFor(string $playerId): ?string
    {
        return in_array($playerId, $this->withoutAccounts, strict: true) ? null : self::keyOf($playerId);
    }

    /**
     * Deterministic so tests can predict it, but carrying no trace of the name —
     * a stub that returned "key-alice" would let a test claiming facts hold no
     * names pass while they plainly did.
     */
    public static function keyOf(string $playerId): string
    {
        return md5($playerId);
    }
}
