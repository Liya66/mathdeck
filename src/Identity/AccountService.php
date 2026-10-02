<?php

declare(strict_types=1);

namespace MathDeck\Identity;

use MathDeck\Identity\Exception\AccountNotFound;
use MathDeck\Identity\Exception\AuthenticationFailed;
use MathDeck\Identity\Exception\InvalidAccountDetails;
use MathDeck\Identity\Exception\PlayerIdAlreadyTaken;
use MathDeck\Identity\Port\AccountStore;

/**
 * Making and maintaining accounts.
 *
 * The model is a teacher provisioning a class, not children signing themselves up:
 * there is no self-registration endpoint, and a teacher may create **students
 * only**. Teacher accounts come from `bin/create-account`, so there is no path
 * through the API by which an account can grant its own level of access to another.
 * That is also the bootstrapping story — the first teacher is made on the machine.
 */
final readonly class AccountService
{
    private const MIN_PASSCODE_LENGTH = 8;
    private const MAX_PASSCODE_LENGTH = 128;
    private const PLAYER_ID_PATTERN = '/^[a-z0-9]([a-z0-9-]{1,30}[a-z0-9])$/';

    public function __construct(
        private AccountStore $accounts,
        private AccountFactory $factory,
        private PasswordHasher $hasher,
        private PasscodeGenerator $passcodes,
    ) {
    }

    /**
     * @throws PlayerIdAlreadyTaken
     * @throws InvalidAccountDetails
     */
    public function createStudent(string $playerId, string $displayName, ?string $passcode = null): NewAccount
    {
        self::assertUsablePlayerId($playerId);
        self::assertUsableDisplayName($displayName);

        if ($this->accounts->find($playerId) !== null) {
            throw PlayerIdAlreadyTaken::of($playerId);
        }

        $passcode ??= $this->passcodes->generate();
        self::assertUsablePasscode($passcode);

        $account = $this->factory->create($playerId, $displayName, Role::Student, $passcode);
        $this->accounts->save($account);

        return new NewAccount($account, $passcode);
    }

    /**
     * Issues a new passcode for somebody who has forgotten theirs.
     *
     * The old one cannot be recovered — it was never stored — which is the point.
     *
     * @throws AccountNotFound
     */
    public function resetPasscode(string $playerId, ?string $passcode = null): NewAccount
    {
        $existing = $this->accounts->find($playerId) ?? throw AccountNotFound::of($playerId);

        $passcode ??= $this->passcodes->generate();
        self::assertUsablePasscode($passcode);

        $this->accounts->save($this->withPasscode($existing, $passcode));

        return new NewAccount($existing, $passcode);
    }

    /**
     * @throws AccountNotFound
     * @throws AuthenticationFailed when the current passcode is wrong
     */
    public function changeOwnPasscode(string $playerId, string $currentPasscode, string $newPasscode): void
    {
        $account = $this->accounts->find($playerId) ?? throw AccountNotFound::of($playerId);

        if (!$this->hasher->verify($currentPasscode, $account->passwordHash)) {
            throw AuthenticationFailed::credentials();
        }

        self::assertUsablePasscode($newPasscode);

        $this->accounts->save($this->withPasscode($account, $newPasscode));
    }

    /** @return list<Account> */
    public function all(): array
    {
        return $this->accounts->all();
    }

    public function get(string $playerId): Account
    {
        return $this->accounts->find($playerId) ?? throw AccountNotFound::of($playerId);
    }

    /**
     * Keeps the analytics key and the creation date. Rotating the key on a passcode
     * change would orphan every attempt already recorded for that child.
     */
    private function withPasscode(Account $account, string $passcode): Account
    {
        return new Account(
            playerId: $account->playerId,
            displayName: $account->displayName,
            role: $account->role,
            passwordHash: $this->hasher->hash($passcode),
            analyticsKey: $account->analyticsKey,
            createdAt: $account->createdAt,
        );
    }

    private static function assertUsablePlayerId(string $playerId): void
    {
        if (preg_match(self::PLAYER_ID_PATTERN, $playerId) !== 1) {
            throw InvalidAccountDetails::because(
                'A sign-in name is 3 to 32 characters of lowercase letters, digits and dashes, '
                . 'starting and ending with a letter or digit.',
            );
        }
    }

    private static function assertUsableDisplayName(string $displayName): void
    {
        $length = mb_strlen(trim($displayName));

        if ($length < 1 || $length > 120) {
            throw InvalidAccountDetails::because('A display name is 1 to 120 characters.');
        }
    }

    private static function assertUsablePasscode(string $passcode): void
    {
        $length = mb_strlen($passcode);

        if ($length < self::MIN_PASSCODE_LENGTH || $length > self::MAX_PASSCODE_LENGTH) {
            throw InvalidAccountDetails::because(sprintf(
                'A passcode is %d to %d characters.',
                self::MIN_PASSCODE_LENGTH,
                self::MAX_PASSCODE_LENGTH,
            ));
        }
    }
}
