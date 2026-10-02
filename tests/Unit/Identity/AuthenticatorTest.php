<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Identity;

use MathDeck\Identity\AccountFactory;
use MathDeck\Identity\Authenticator;
use MathDeck\Identity\Exception\AuthenticationFailed;
use MathDeck\Identity\PasswordHasher;
use MathDeck\Identity\Role;
use MathDeck\Identity\TokenIssuer;
use MathDeck\Infrastructure\InMemory\InMemoryAccountStore;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class AuthenticatorTest extends TestCase
{
    private InMemoryAccountStore $accounts;
    private Authenticator $authenticator;

    protected function setUp(): void
    {
        $clock = new FrozenClock();
        $hasher = new PasswordHasher();

        $this->accounts = new InMemoryAccountStore();
        $this->accounts->save(
            (new AccountFactory($hasher, $clock))->create('alice', 'Alice', Role::Student, 'open-sesame'),
        );

        $this->authenticator = new Authenticator(
            $this->accounts,
            $hasher,
            new TokenIssuer('a-secret-long-enough-to-be-taken-seriously', $clock),
        );
    }

    public function testCorrectCredentialsIssueAToken(): void
    {
        $token = $this->authenticator->signIn('alice', 'open-sesame');

        self::assertSame('alice', $token->playerId);
        self::assertSame(Role::Student, $token->role);
        self::assertNotSame('', $token->value);
    }

    /**
     * The two failure modes must be indistinguishable, or the endpoint becomes a way
     * to find out who has an account.
     */
    public function testAWrongPasscodeAndAnUnknownAccountFailIdentically(): void
    {
        $wrongPasscode = null;
        $unknownAccount = null;

        try {
            $this->authenticator->signIn('alice', 'not-the-passcode');
        } catch (AuthenticationFailed $failed) {
            $wrongPasscode = $failed->getMessage();
        }

        try {
            $this->authenticator->signIn('nobody', 'not-the-passcode');
        } catch (AuthenticationFailed $failed) {
            $unknownAccount = $failed->getMessage();
        }

        self::assertNotNull($wrongPasscode);
        self::assertSame($wrongPasscode, $unknownAccount);
    }

    public function testThePasscodeIsNeverStored(): void
    {
        $account = $this->accounts->find('alice');

        self::assertNotNull($account);
        self::assertStringNotContainsString('open-sesame', $account->passwordHash);
        self::assertStringStartsWith('$', $account->passwordHash, 'A hash, not a passcode.');
    }

    public function testTwoAccountsWithTheSamePasscodeGetDifferentHashes(): void
    {
        $hasher = new PasswordHasher();

        self::assertNotSame($hasher->hash('same'), $hasher->hash('same'), 'Unsalted hashes would match.');
        self::assertTrue($hasher->verify('same', $hasher->hash('same')));
    }

    /** The analytics key is a pseudonym, not a transformation of the name. */
    public function testEachAccountGetsItsOwnAnalyticsKey(): void
    {
        $factory = new AccountFactory(new PasswordHasher(), new FrozenClock());

        $first = $factory->create('alice', 'Alice', Role::Student, 'x');
        $second = $factory->create('alice', 'Alice', Role::Student, 'x');

        self::assertNotSame($first->analyticsKey, $second->analyticsKey);
        self::assertStringNotContainsString('alice', $first->analyticsKey);
        self::assertSame(32, strlen($first->analyticsKey));
    }
}
