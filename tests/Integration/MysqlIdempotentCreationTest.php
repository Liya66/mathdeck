<?php

declare(strict_types=1);

namespace MathDeck\Tests\Integration;

use MathDeck\Application\Exception\DuplicateMatchCreation;
use MathDeck\Application\MatchRecord;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Identity\Port\SignInAttempts;
use MathDeck\Infrastructure\Mysql\MysqlMatchStore;
use MathDeck\Infrastructure\Mysql\MysqlSignInAttempts;

/**
 * The two phase-8 follow-ups, against the database where their guarantees live:
 * a unique index and a windowed count.
 */
final class MysqlIdempotentCreationTest extends MysqlTestCase
{
    private MysqlMatchStore $matches;
    private SignInAttempts $attempts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->exec('DELETE FROM sign_in_attempts');
        $this->matches = new MysqlMatchStore($this->connection);
        $this->attempts = new MysqlSignInAttempts($this->connection);
    }

    /**
     * The guarantee is the unique index, not the check before the insert. Two
     * requests that both pass the check must not both create a match.
     */
    public function testASecondMatchWithTheSameCreationKeyIsRefusedByTheDatabase(): void
    {
        $this->matches->save(self::record('m1', 'tapped-twice'));

        $this->expectException(DuplicateMatchCreation::class);

        $this->matches->save(self::record('m2', 'tapped-twice'));
    }

    public function testTheMatchCanBeReadBackByItsCreationKey(): void
    {
        $this->matches->save(self::record('m1', 'tapped-twice'));

        $found = $this->matches->findByCreationKey('tapped-twice');

        self::assertNotNull($found);
        self::assertSame('m1', $found->matchId);
        self::assertSame('tapped-twice', $found->creationKey);
        self::assertNull($this->matches->findByCreationKey('never-sent'));
    }

    /** Matches made before phase 8 have no key, and a null must not collide. */
    public function testSeveralMatchesWithNoCreationKeyCoexist(): void
    {
        $this->matches->save(self::record('m1', null));
        $this->matches->save(self::record('m2', null));

        self::assertCount(2, $this->matches->allMatchIds());
        self::assertNull($this->matches->find('m1')?->creationKey);
    }

    public function testFailuresAreCountedWithinTheWindowOnly(): void
    {
        $now = new \DateTimeImmutable('2026-03-01 09:00:00', new \DateTimeZone('UTC'));

        $this->attempts->recordFailure('account:ada', $now->modify('-10 minutes'));
        $this->attempts->recordFailure('account:ada', $now->modify('-1 minute'));
        $this->attempts->recordFailure('account:ada', $now);

        self::assertSame(2, $this->attempts->failuresSince('account:ada', $now->modify('-5 minutes')));
        self::assertSame(3, $this->attempts->failuresSince('account:ada', $now->modify('-1 hour')));
        self::assertSame(0, $this->attempts->failuresSince('account:ben', $now->modify('-1 hour')));
    }

    public function testClearingOneBucketLeavesTheOthers(): void
    {
        $now = new \DateTimeImmutable('2026-03-01 09:00:00', new \DateTimeZone('UTC'));

        $this->attempts->recordFailure('account:ada', $now);
        $this->attempts->recordFailure('address:10.0.0.1', $now);

        $this->attempts->clear('account:ada');

        self::assertSame(0, $this->attempts->failuresSince('account:ada', $now->modify('-1 hour')));
        self::assertSame(1, $this->attempts->failuresSince('address:10.0.0.1', $now->modify('-1 hour')));
    }

    /** Old rows are swept on write, so the table needs no scheduled job. */
    public function testFailuresOlderThanADayAreSweptAway(): void
    {
        $now = new \DateTimeImmutable('2026-03-01 09:00:00', new \DateTimeZone('UTC'));

        $this->attempts->recordFailure('account:ada', $now->modify('-2 days'));
        self::assertSame(1, $this->attempts->failuresSince('account:ada', new \DateTimeImmutable('@0')));

        $this->attempts->recordFailure('account:ada', $now);

        self::assertSame(
            1,
            $this->attempts->failuresSince('account:ada', new \DateTimeImmutable('@0')),
            'The two-day-old row went when the new one landed.',
        );
    }

    private static function record(string $matchId, ?string $creationKey): MatchRecord
    {
        return new MatchRecord(
            matchId: $matchId,
            deckVersionId: 'starter@1',
            seed: 1,
            playerIds: ['ada', 'ben'],
            rules: DeckRules::default(),
            createdAt: new \DateTimeImmutable('2026-03-01 09:00:00', new \DateTimeZone('UTC')),
            creationKey: $creationKey,
        );
    }
}
