<?php

declare(strict_types=1);

namespace MathDeck\Identity;

use MathDeck\Engine\Clock;
use MathDeck\Identity\Exception\AuthenticationFailed;

/**
 * Signed, expiring bearer tokens.
 *
 * Until now the token *was* the player id, which authenticated nobody — anyone
 * could be anyone by typing their name. The boundary that decision established was
 * real; this is the check that finally sits behind it.
 *
 * Self-contained and HMAC-signed, so verifying costs no database round trip. The
 * trade is that a token cannot be revoked before it expires, which is why the
 * lifetime is short rather than convenient.
 */
final readonly class TokenIssuer
{
    private const VERSION = 'v1';
    private const MINIMUM_SECRET_LENGTH = 32;

    public function __construct(
        private string $secret,
        private Clock $clock,
        private int $lifetimeSeconds = 12 * 3600,
    ) {
        // A deployment that forgot to configure a secret must fail at boot, not sign
        // every token with the empty string and look like it is working.
        if (strlen($this->secret) < self::MINIMUM_SECRET_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'The token secret must be at least %d characters; got %d.',
                self::MINIMUM_SECRET_LENGTH,
                strlen($this->secret),
            ));
        }
    }

    public function issue(Account $account): Token
    {
        $issuedAt = $this->clock->now();
        $expiresAt = $issuedAt->modify(sprintf('+%d seconds', $this->lifetimeSeconds));

        $payload = self::encode(json_encode([
            'sub' => $account->playerId,
            'role' => $account->role->value,
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        $body = self::VERSION . '.' . $payload;

        return new Token(
            value: $body . '.' . self::encode($this->sign($body)),
            playerId: $account->playerId,
            role: $account->role,
            expiresAt: $expiresAt,
        );
    }

    /** @throws AuthenticationFailed */
    public function verify(string $value): Token
    {
        $parts = explode('.', $value);

        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            throw AuthenticationFailed::token('That token is not in a form I recognise.');
        }

        [$version, $payload, $signature] = $parts;

        // hash_equals, never ===: a byte-by-byte comparison that returns early leaks
        // how much of a forged signature was right.
        if (!hash_equals($this->sign($version . '.' . $payload), self::decode($signature))) {
            throw AuthenticationFailed::token('That token has been tampered with.');
        }

        $claims = json_decode(self::decode($payload), associative: true);

        if (!is_array($claims) || !is_string($claims['sub'] ?? null) || !is_string($claims['role'] ?? null)) {
            throw AuthenticationFailed::token('That token is missing its claims.');
        }

        $expiresAt = (new \DateTimeImmutable('@' . (int) ($claims['exp'] ?? 0)))
            ->setTimezone(new \DateTimeZone('UTC'));

        if ($expiresAt <= $this->clock->now()) {
            throw AuthenticationFailed::token('That token has expired. Sign in again.');
        }

        $role = Role::tryFrom($claims['role']);

        if ($role === null) {
            throw AuthenticationFailed::token('That token carries a role I do not know.');
        }

        return new Token($value, $claims['sub'], $role, $expiresAt);
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret, binary: true);
    }

    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function decode(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'), strict: false);
    }
}
