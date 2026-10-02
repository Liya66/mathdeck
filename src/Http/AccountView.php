<?php

declare(strict_types=1);

namespace MathDeck\Http;

use MathDeck\Identity\Account;

/**
 * What an account looks like over the wire.
 *
 * No hash, no analytics key. The hash because it is a credential even in its hashed
 * form; the analytics key because the whole point of it is that the side holding
 * attempt data cannot put a name to one — handing it out beside the name here would
 * undo that in a single response.
 */
final readonly class AccountView
{
    /** @return array<string, mixed> */
    public static function of(Account $account): array
    {
        return [
            'playerId' => $account->playerId,
            'displayName' => $account->displayName,
            'role' => $account->role->value,
            'createdAt' => $account->createdAt->format('Y-m-d\TH:i:s.up'),
        ];
    }
}
