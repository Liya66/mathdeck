<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Identity;

use MathDeck\Identity\Account;
use MathDeck\Identity\Exception\AuthenticationFailed;
use MathDeck\Identity\Role;
use MathDeck\Identity\TokenIssuer;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class TokenIssuerTest extends TestCase
{
    private const SECRET = 'a-secret-long-enough-to-be-taken-seriously';

    private FrozenClock $clock;
    private TokenIssuer $issuer;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->issuer = new TokenIssuer(self::SECRET, $this->clock);
    }

    public function testATokenRoundTrips(): void
    {
        $token = $this->issuer->verify($this->issuer->issue(self::teacher())->value);

        self::assertSame('miss-lee', $token->playerId);
        self::assertSame(Role::Teacher, $token->role);
    }

    /**
     * The role is in the signed payload, so a student cannot become a teacher by
     * editing anything they hold.
     */
    public function testEditingTheClaimsInvalidatesTheToken(): void
    {
        $token = $this->issuer->issue(self::teacher())->value;
        [$version, $payload, $signature] = explode('.', $token);

        $claims = json_decode(
            (string) base64_decode(strtr($payload, '-_', '+/'), strict: false),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($claims);

        $claims['role'] = 'teacher';
        $claims['sub'] = 'mallory';
        $forged = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $this->expectException(AuthenticationFailed::class);

        $this->issuer->verify("{$version}.{$forged}.{$signature}");
    }

    public function testATokenSignedWithAnotherSecretIsRefused(): void
    {
        $foreign = new TokenIssuer('a-completely-different-secret-of-good-length', $this->clock);

        $this->expectException(AuthenticationFailed::class);

        $this->issuer->verify($foreign->issue(self::teacher())->value);
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $token = (new TokenIssuer(self::SECRET, $this->clock, lifetimeSeconds: 60))->issue(self::teacher());

        $this->clock->advanceMs(61_000);

        $this->expectException(AuthenticationFailed::class);

        $this->issuer->verify($token->value);
    }

    public function testATokenStillValidJustBeforeExpiryIsAccepted(): void
    {
        $token = (new TokenIssuer(self::SECRET, $this->clock, lifetimeSeconds: 60))->issue(self::teacher());

        $this->clock->advanceMs(59_000);

        self::assertSame('miss-lee', $this->issuer->verify($token->value)->playerId);
    }

    /** @param string $token */
    #[\PHPUnit\Framework\Attributes\DataProvider('malformedTokens')]
    public function testMalformedTokensAreRefused(string $token): void
    {
        $this->expectException(AuthenticationFailed::class);

        $this->issuer->verify($token);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'not a token at all' => ['alice'];
        yield 'two parts' => ['v1.abc'];
        yield 'unknown version' => ['v9.abc.def'];
        yield 'garbage signature' => ['v1.abc.def'];
    }

    /**
     * A deployment that forgot to configure a secret must fail at boot, not sign
     * every token with the empty string and look like it is working.
     */
    public function testAWeakSecretIsRefusedAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TokenIssuer('short', $this->clock);
    }

    private static function teacher(): Account
    {
        return new Account(
            playerId: 'miss-lee',
            displayName: 'Miss Lee',
            role: Role::Teacher,
            passwordHash: 'irrelevant',
            analyticsKey: str_repeat('a', 32),
            createdAt: new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC')),
        );
    }
}
