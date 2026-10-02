<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Identity;

use MathDeck\Identity\AccountFactory;
use MathDeck\Identity\Authenticator;
use MathDeck\Identity\Exception\AuthenticationFailed;
use MathDeck\Identity\Exception\TooManyAttempts;
use MathDeck\Identity\PasswordHasher;
use MathDeck\Identity\SignInThrottle;
use MathDeck\Identity\Role;
use MathDeck\Identity\TokenIssuer;
use MathDeck\Infrastructure\InMemory\InMemoryAccountStore;
use MathDeck\Infrastructure\InMemory\InMemorySignInAttempts;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class AuthenticatorTest extends TestCase
{
    private InMemoryAccountStore $accounts;
    private Authenticator $authenticator;
    private InMemorySignInAttempts $attempts;

    protected function setUp(): void
    {
        $clock = new FrozenClock();
        $hasher = new PasswordHasher();
        $this->attempts = new InMemorySignInAttempts();

        $this->accounts = new InMemoryAccountStore();
        $this->accounts->save(
            (new AccountFactory($hasher, $clock))->create('alice', 'Alice', Role::Student, 'open-sesame'),
        );

        $this->authenticator = new Authenticator(
            $this->accounts,
            $hasher,
            new TokenIssuer('a-secret-long-enough-to-be-taken-seriously', $clock),
            new SignInThrottle($this->attempts, $clock, maxPerAccount: 3, maxPerAddress: 5),
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

    /**
     * Classroom passcodes are short and memorable by design — `play-1234` falls to
     * a few thousand guesses. The throttle is what makes that choice defensible.
     */
    public function testGuessingIsCutOffAfterAFewTries(): void
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                $this->authenticator->signIn('alice', 'guess', '10.0.0.1');
            } catch (AuthenticationFailed) {
                // Expected: three wrong guesses are allowed through.
            }
        }

        $this->expectException(TooManyAttempts::class);

        $this->authenticator->signIn('alice', 'guess', '10.0.0.1');
    }

    /** Even the right passcode is refused once the budget is spent. */
    public function testTheCorrectPasscodeIsAlsoRefusedWhileThrottled(): void
    {
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                $this->authenticator->signIn('alice', 'guess', '10.0.0.1');
            } catch (AuthenticationFailed) {
                // Spending the budget.
            }
        }

        $this->expectException(TooManyAttempts::class);

        $this->authenticator->signIn('alice', 'open-sesame', '10.0.0.1');
    }

    /** A fumbled passcode followed by the right one must not leave a mark. */
    public function testSigningInSuccessfullyClearsTheAccountsBudget(): void
    {
        try {
            $this->authenticator->signIn('alice', 'guess', '10.0.0.1');
        } catch (AuthenticationFailed) {
            // One slip.
        }

        $this->authenticator->signIn('alice', 'open-sesame', '10.0.0.1');

        $refusals = [];

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                $this->authenticator->signIn('alice', 'guess', '10.0.0.1');
            } catch (\RuntimeException $failure) {
                $refusals[] = $failure::class;
            }
        }

        // Three more guesses were judged on the passcode, not cut off by the
        // throttle — which is what "the budget was cleared" means.
        self::assertSame(array_fill(0, 3, AuthenticationFailed::class), $refusals);
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
