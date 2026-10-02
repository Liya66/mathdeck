<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Identity;

use MathDeck\Identity\AccountFactory;
use MathDeck\Identity\AccountService;
use MathDeck\Identity\Exception\AccountNotFound;
use MathDeck\Identity\Exception\AuthenticationFailed;
use MathDeck\Identity\Exception\InvalidAccountDetails;
use MathDeck\Identity\Exception\PlayerIdAlreadyTaken;
use MathDeck\Identity\PasscodeGenerator;
use MathDeck\Identity\PasswordHasher;
use MathDeck\Identity\Role;
use MathDeck\Infrastructure\InMemory\InMemoryAccountStore;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountServiceTest extends TestCase
{
    private InMemoryAccountStore $accounts;
    private PasswordHasher $hasher;
    private AccountService $service;

    protected function setUp(): void
    {
        $this->accounts = new InMemoryAccountStore();
        $this->hasher = new PasswordHasher();
        $this->service = new AccountService(
            $this->accounts,
            new AccountFactory($this->hasher, new FrozenClock()),
            $this->hasher,
            new PasscodeGenerator(),
        );
    }

    public function testACreatedStudentCanSignInWithTheIssuedPasscode(): void
    {
        $created = $this->service->createStudent('ada', 'Ada');

        self::assertSame(Role::Student, $created->account->role);
        self::assertTrue($this->hasher->verify($created->passcode, $created->account->passwordHash));
    }

    /** The passcode exists in the response and nowhere else. */
    public function testThePasscodeIsNotRecoverableAfterwards(): void
    {
        $created = $this->service->createStudent('ada', 'Ada');

        $stored = $this->accounts->find('ada');

        self::assertNotNull($stored);
        self::assertStringNotContainsString($created->passcode, $stored->passwordHash);
        self::assertStringStartsWith('$', $stored->passwordHash);
    }

    public function testAGeneratedPasscodeIsLongEnoughToBeAcceptedBackAsOne(): void
    {
        $passcode = $this->service->createStudent('ada', 'Ada')->passcode;

        self::assertMatchesRegularExpression('/^[a-z]+-[a-z]+-\d{3}$/', $passcode);
        self::assertGreaterThanOrEqual(8, strlen($passcode));
    }

    public function testASuppliedPasscodeIsUsedAsGiven(): void
    {
        $created = $this->service->createStudent('ada', 'Ada', 'chosen-by-the-teacher');

        self::assertSame('chosen-by-the-teacher', $created->passcode);
        self::assertTrue($this->hasher->verify('chosen-by-the-teacher', $created->account->passwordHash));
    }

    public function testASignInNameCannotBeTakenTwice(): void
    {
        $this->service->createStudent('ada', 'Ada');

        $this->expectException(PlayerIdAlreadyTaken::class);

        $this->service->createStudent('ada', 'Ada Lovelace');
    }

    #[DataProvider('unusableDetails')]
    public function testUnusableDetailsAreRefused(string $playerId, string $displayName, ?string $passcode): void
    {
        $this->expectException(InvalidAccountDetails::class);

        $this->service->createStudent($playerId, $displayName, $passcode);
    }

    /** @return iterable<string, array{string, string, string|null}> */
    public static function unusableDetails(): iterable
    {
        yield 'name too short' => ['ad', 'Ada', null];
        yield 'uppercase' => ['Ada', 'Ada', null];
        yield 'spaces' => ['ada lovelace', 'Ada', null];
        yield 'leading dash' => ['-ada', 'Ada', null];
        yield 'trailing dash' => ['ada-', 'Ada', null];
        yield 'empty display name' => ['ada', '   ', null];
        yield 'passcode too short' => ['ada', 'Ada', 'short'];
    }

    /**
     * Resetting must not rotate the analytics key — every attempt already recorded
     * for this child is filed under it.
     */
    public function testAResetKeepsThePseudonymAndTheCreationDate(): void
    {
        $original = $this->service->createStudent('ada', 'Ada')->account;

        $this->service->resetPasscode('ada');
        $after = $this->accounts->find('ada');

        self::assertNotNull($after);
        self::assertSame($original->analyticsKey, $after->analyticsKey);
        self::assertEquals($original->createdAt, $after->createdAt);
        self::assertSame($original->displayName, $after->displayName);
    }

    public function testAResetInvalidatesTheOldPasscode(): void
    {
        $old = $this->service->createStudent('ada', 'Ada')->passcode;

        $new = $this->service->resetPasscode('ada')->passcode;
        $stored = $this->accounts->find('ada');

        self::assertNotNull($stored);
        self::assertNotSame($old, $new);
        self::assertFalse($this->hasher->verify($old, $stored->passwordHash));
        self::assertTrue($this->hasher->verify($new, $stored->passwordHash));
    }

    public function testResettingSomebodyWhoDoesNotExistIsReported(): void
    {
        $this->expectException(AccountNotFound::class);

        $this->service->resetPasscode('nobody');
    }

    public function testChangingYourOwnPasscodeNeedsTheCurrentOne(): void
    {
        $created = $this->service->createStudent('ada', 'Ada');

        $this->service->changeOwnPasscode('ada', $created->passcode, 'my-new-passcode');

        $stored = $this->accounts->find('ada');
        self::assertNotNull($stored);
        self::assertTrue($this->hasher->verify('my-new-passcode', $stored->passwordHash));
    }

    public function testTheWrongCurrentPasscodeChangesNothing(): void
    {
        $created = $this->service->createStudent('ada', 'Ada');

        try {
            $this->service->changeOwnPasscode('ada', 'not-the-passcode', 'my-new-passcode');
            self::fail('Expected a refusal.');
        } catch (AuthenticationFailed) {
            $stored = $this->accounts->find('ada');
            self::assertNotNull($stored);
            self::assertTrue($this->hasher->verify($created->passcode, $stored->passwordHash));
        }
    }

    public function testAWeakNewPasscodeIsRefused(): void
    {
        $created = $this->service->createStudent('ada', 'Ada');

        $this->expectException(InvalidAccountDetails::class);

        $this->service->changeOwnPasscode('ada', $created->passcode, 'short');
    }

    public function testTheClassListIsOrderedAndCarriesNoSecret(): void
    {
        $this->service->createStudent('ben', 'Ben');
        $this->service->createStudent('ada', 'Ada');

        $all = $this->service->all();

        self::assertSame(['ada', 'ben'], array_column(array_map(
            static fn ($account): array => ['playerId' => $account->playerId],
            $all,
        ), 'playerId'));
    }
}
