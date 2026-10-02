<?php

declare(strict_types=1);

namespace MathDeck\Identity;

/**
 * Argon2id where the build supports it, bcrypt otherwise.
 *
 * The official PHP images are not all compiled with argon2, and an application that
 * refuses to start on half of them is worse than one that uses a strong bcrypt. The
 * algorithm is recorded in the hash itself, so accounts created under one can be
 * verified — and transparently rehashed — under the other.
 */
final readonly class PasswordHasher
{
    private const BCRYPT_COST = 12;

    public function hash(string $passcode): string
    {
        [$algorithm, $options] = self::algorithm();

        // password_hash returns a string or throws in PHP 8; there is no failure
        // value left to guard against.
        return password_hash($passcode, $algorithm, $options);
    }

    public function verify(string $passcode, string $hash): bool
    {
        // password_verify is constant-time for a given hash; the algorithm is read
        // from the hash, so an old bcrypt hash still verifies after a move to argon.
        return password_verify($passcode, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        [$algorithm, $options] = self::algorithm();

        return password_needs_rehash($hash, $algorithm, $options);
    }

    /** @return array{0: string, 1: array<string, int>} */
    private static function algorithm(): array
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return [PASSWORD_ARGON2ID, []];
        }

        return [PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]];
    }
}
