<?php

declare(strict_types=1);

namespace MathDeck\Identity\Port;

use MathDeck\Identity\Account;

interface AccountStore
{
    public function save(Account $account): void;

    public function find(string $playerId): ?Account;

    /**
     * Display names for a set of analytics keys. The only route back from a
     * pseudonym to a person, and the caller must already be authorised.
     *
     * @param list<string> $analyticsKeys
     *
     * @return array<string, string> analyticsKey => displayName
     */
    public function displayNamesFor(array $analyticsKeys): array;

    public function analyticsKeyFor(string $playerId): ?string;
}
