<?php

declare(strict_types=1);

namespace MathDeck\Identity;

use MathDeck\Identity\Exception\AuthenticationFailed;
use MathDeck\Identity\Port\AccountStore;

final readonly class Authenticator
{
    /**
     * A bcrypt hash of nothing in particular. Verifying against it when no account
     * exists keeps the failure path roughly as slow as the success path, so the
     * endpoint cannot be timed to discover who has an account.
     */
    private const DECOY_HASH = '$2y$12$C6UzMDM.H6dfI/f/IKcEe.2/lO7pFn8Zc8PLiQm/qZ7EL8rpTuSeO';

    public function __construct(
        private AccountStore $accounts,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
    ) {
    }

    /** @throws AuthenticationFailed */
    public function signIn(string $playerId, string $passcode): Token
    {
        $account = $this->accounts->find($playerId);

        if ($account === null) {
            $this->hasher->verify($passcode, self::DECOY_HASH);

            throw AuthenticationFailed::credentials();
        }

        if (!$this->hasher->verify($passcode, $account->passwordHash)) {
            throw AuthenticationFailed::credentials();
        }

        if ($this->hasher->needsRehash($account->passwordHash)) {
            $this->accounts->save(new Account(
                playerId: $account->playerId,
                displayName: $account->displayName,
                role: $account->role,
                passwordHash: $this->hasher->hash($passcode),
                analyticsKey: $account->analyticsKey,
                createdAt: $account->createdAt,
            ));
        }

        return $this->tokens->issue($account);
    }
}
