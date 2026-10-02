<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Identity;

use MathDeck\Identity\Exception\TooManyAttempts;
use MathDeck\Identity\SignInThrottle;
use MathDeck\Infrastructure\InMemory\InMemorySignInAttempts;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class SignInThrottleTest extends TestCase
{
    private FrozenClock $clock;
    private InMemorySignInAttempts $attempts;
    private SignInThrottle $throttle;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->attempts = new InMemorySignInAttempts();
        $this->throttle = new SignInThrottle(
            $this->attempts,
            $this->clock,
            maxPerAccount: 3,
            maxPerAddress: 5,
            windowSeconds: 300,
        );
    }

    public function testAFreshAccountIsLetThrough(): void
    {
        $this->throttle->check('alice', '10.0.0.1');

        self::assertSame(0, $this->attempts->failuresSince('account:alice', $this->windowStart()));
        self::assertSame(0, $this->attempts->failuresSince('address:10.0.0.1', $this->windowStart()));
    }

    public function testTheAccountBudgetRefusesAtTheLimit(): void
    {
        $this->failTimes(3, 'alice', '10.0.0.1');

        $this->expectException(TooManyAttempts::class);

        $this->throttle->check('alice', '10.0.0.1');
    }

    /**
     * Without this bucket, an attacker simply spreads one guess across every
     * account instead of grinding at one.
     */
    public function testTheAddressBudgetCatchesSprayingAcrossAccounts(): void
    {
        foreach (['alice', 'bob', 'carol', 'dev', 'erin'] as $playerId) {
            $this->throttle->recordFailure($playerId, '10.0.0.1');
        }

        // No single account is near its own limit...
        $this->throttle->check('frank', '10.0.0.2');

        // ...but that address has spent its budget.
        $this->expectException(TooManyAttempts::class);

        $this->throttle->check('frank', '10.0.0.1');
    }

    /**
     * Guessing one account correctly must not refund the budget being spent
     * against all the others.
     */
    public function testASuccessClearsTheAccountButNotTheAddress(): void
    {
        foreach (['alice', 'bob', 'carol', 'dev', 'erin'] as $playerId) {
            $this->throttle->recordFailure($playerId, '10.0.0.1');
        }

        $this->throttle->recordSuccess('alice');

        // Alice herself is clear again.
        self::assertSame(0, $this->attempts->failuresSince('account:alice', $this->windowStart()));

        // The address is not.
        $this->expectException(TooManyAttempts::class);

        $this->throttle->check('alice', '10.0.0.1');
    }

    public function testTheWindowSlidesSoAGuesserHasToWaitRatherThanBeBannedForever(): void
    {
        $this->failTimes(3, 'alice', '10.0.0.1');

        $this->clock->advanceMs(299_000);

        try {
            $this->throttle->check('alice', '10.0.0.1');
            self::fail('Still inside the window.');
        } catch (TooManyAttempts $refused) {
            self::assertSame(300, $refused->retryAfterSeconds);
        }

        $this->clock->advanceMs(2_000);

        $this->throttle->check('alice', '10.0.0.1');

        // The failures are still on record; they have simply aged out of the window.
        self::assertSame(0, $this->attempts->failuresSince('account:alice', $this->windowStart()));
        self::assertSame(3, $this->attempts->failuresSince('account:alice', new \DateTimeImmutable('@0')));
    }

    /** A caller with no usable address is still throttled by account. */
    public function testAMissingClientAddressDoesNotDisableTheThrottle(): void
    {
        $this->failTimes(3, 'alice', null);

        $this->expectException(TooManyAttempts::class);

        $this->throttle->check('alice', null);
    }

    public function testFailuresAgainstOneAccountDoNotAffectAnother(): void
    {
        $this->failTimes(3, 'alice', '10.0.0.1');

        $this->throttle->check('bob', '10.0.0.2');

        self::assertSame(3, $this->attempts->failuresSince('account:alice', $this->windowStart()));
        self::assertSame(0, $this->attempts->failuresSince('account:bob', $this->windowStart()));
    }

    private function failTimes(int $times, string $playerId, ?string $address): void
    {
        for ($attempt = 0; $attempt < $times; ++$attempt) {
            $this->throttle->recordFailure($playerId, $address);
        }
    }

    private function windowStart(): \DateTimeImmutable
    {
        return $this->clock->now()->modify('-300 seconds');
    }
}
