<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Identity\Account;
use MathDeck\Identity\Port\AccountStore;

final class InMemoryAccountStore implements AccountStore
{
    /** @var array<string, Account> */
    private array $accounts = [];

    public function save(Account $account): void
    {
        $this->accounts[$account->playerId] = $account;
    }

    public function find(string $playerId): ?Account
    {
        return $this->accounts[$playerId] ?? null;
    }

    public function all(): array
    {
        $all = array_values($this->accounts);

        usort($all, static fn (Account $a, Account $b): int => $a->playerId <=> $b->playerId);

        return $all;
    }

    public function displayNamesFor(array $analyticsKeys): array
    {
        $names = [];

        foreach ($this->accounts as $account) {
            if (in_array($account->analyticsKey, $analyticsKeys, strict: true)) {
                $names[$account->analyticsKey] = $account->displayName;
            }
        }

        return $names;
    }

    public function analyticsKeyFor(string $playerId): ?string
    {
        return ($this->accounts[$playerId] ?? null)?->analyticsKey;
    }
}
