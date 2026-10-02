<?php

declare(strict_types=1);

namespace MathDeck\Tests\Integration;

use MathDeck\Identity\AccountFactory;
use MathDeck\Identity\PasswordHasher;
use MathDeck\Identity\Role;
use MathDeck\Infrastructure\Mysql\MysqlAccountStore;
use MathDeck\Tests\Support\FrozenClock;

final class MysqlAccountStoreTest extends MysqlTestCase
{
    private MysqlAccountStore $store;
    private AccountFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->exec('DELETE FROM accounts');
        $this->store = new MysqlAccountStore($this->connection);
        $this->factory = new AccountFactory(new PasswordHasher(), new FrozenClock());
    }

    public function testAnAccountSurvivesARoundTrip(): void
    {
        $account = $this->factory->create('miss-lee', 'Miss Lee', Role::Teacher, 'teach-me');
        $this->store->save($account);

        $loaded = $this->store->find('miss-lee');

        self::assertNotNull($loaded);
        self::assertSame('Miss Lee', $loaded->displayName);
        self::assertSame(Role::Teacher, $loaded->role);
        self::assertSame($account->analyticsKey, $loaded->analyticsKey);
        self::assertTrue((new PasswordHasher())->verify('teach-me', $loaded->passwordHash));
    }

    public function testThePasscodeIsNeverWrittenToTheDatabase(): void
    {
        $this->store->save($this->factory->create('ada', 'Ada', Role::Student, 'open-sesame'));

        $statement = $this->connection->query('SELECT password_hash FROM accounts WHERE player_id = "ada"');
        $hash = $statement === false ? '' : (string) $statement->fetchColumn();

        self::assertStringNotContainsString('open-sesame', $hash);
        self::assertStringStartsWith('$', $hash);
    }

    /** Two accounts must never collide on the key analytics stores them under. */
    public function testAnalyticsKeysAreUnique(): void
    {
        $this->store->save($this->factory->create('ada', 'Ada', Role::Student, 'x'));
        $this->store->save($this->factory->create('ben', 'Ben', Role::Student, 'x'));

        $statement = $this->connection->query('SELECT COUNT(DISTINCT analytics_key) FROM accounts');

        self::assertSame(2, $statement === false ? 0 : (int) $statement->fetchColumn());
    }

    public function testPseudonymsResolveBackToNamesOnlyInBulkAndOnlyHere(): void
    {
        $ada = $this->factory->create('ada', 'Ada', Role::Student, 'x');
        $ben = $this->factory->create('ben', 'Ben', Role::Student, 'x');
        $this->store->save($ada);
        $this->store->save($ben);

        // assertEquals, not assertSame: this returns a map, and the query has no
        // ORDER BY because a map has no order. assertSame compares key order too,
        // which made this pass locally and fail in CI on whatever MySQL felt like
        // returning first.
        self::assertEquals(
            [$ada->analyticsKey => 'Ada', $ben->analyticsKey => 'Ben'],
            $this->store->displayNamesFor([$ada->analyticsKey, $ben->analyticsKey]),
        );
        self::assertSame([], $this->store->displayNamesFor([]));
        self::assertSame($ada->analyticsKey, $this->store->analyticsKeyFor('ada'));
        self::assertNull($this->store->analyticsKeyFor('nobody'));
    }

    public function testSavingAgainUpdatesTheAccountWithoutChangingItsPseudonym(): void
    {
        $original = $this->factory->create('ada', 'Ada', Role::Student, 'x');
        $this->store->save($original);

        $renamed = $this->factory->create('ada', 'Ada Lovelace', Role::Teacher, 'y');
        $this->store->save($renamed);

        $loaded = $this->store->find('ada');

        self::assertNotNull($loaded);
        self::assertSame('Ada Lovelace', $loaded->displayName);
        self::assertSame(Role::Teacher, $loaded->role);
        self::assertSame(
            $original->analyticsKey,
            $loaded->analyticsKey,
            'Rotating the key would orphan every fact already collected.',
        );
    }
}
