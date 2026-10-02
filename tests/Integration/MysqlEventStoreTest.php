<?php

declare(strict_types=1);

namespace MathDeck\Tests\Integration;

use MathDeck\Application\EventSerializer;
use MathDeck\Application\Exception\DuplicateCommand;
use MathDeck\Application\Exception\SequenceConflict;
use MathDeck\Application\MatchRecord;
use MathDeck\Application\StoredEvent;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Card\Operator;
use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;
use MathDeck\Engine\Rule\RejectReason;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Infrastructure\Mysql\MysqlEventStore;
use MathDeck\Infrastructure\Mysql\MysqlMatchStore;

final class MysqlEventStoreTest extends MysqlTestCase
{
    private const MATCH_ID = 'match-integration';

    private MysqlEventStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new MysqlEventStore($this->connection, new EventSerializer());

        (new MysqlMatchStore($this->connection))->save(new MatchRecord(
            matchId: self::MATCH_ID,
            deckVersionId: 'deck-v1',
            seed: 20260115,
            playerIds: ['alice', 'bob'],
            rules: DeckRules::default(),
            createdAt: new \DateTimeImmutable('2026-01-15 09:00:00.000000', new \DateTimeZone('UTC')),
        ));
    }

    public function testEventsComeBackInOrderAndIntact(): void
    {
        $events = self::sampleEvents();

        $this->store->append(self::MATCH_ID, 0, $events, 'cmd-1');
        $stored = $this->store->load(self::MATCH_ID);

        self::assertCount(count($events), $stored);

        foreach ($stored as $index => $entry) {
            self::assertSame($index + 1, $entry->seq);
            self::assertSame($events[$index]::class, $entry->event::class);
            self::assertSame($events[$index]->payload(), $entry->event->payload());
        }
    }

    /** Latency analytics are measured in milliseconds, so DATETIME(6) is not optional. */
    public function testMicrosecondsSurviveTheRoundTrip(): void
    {
        $at = new \DateTimeImmutable('2026-01-15 09:00:00.123456', new \DateTimeZone('UTC'));

        $this->store->append(self::MATCH_ID, 0, [new TargetRevealed(self::MATCH_ID, $at, 12)], 'cmd-1');

        self::assertSame(
            $at->format('Y-m-d H:i:s.u'),
            $this->store->load(self::MATCH_ID)[0]->event->occurredAt()->format('Y-m-d H:i:s.u'),
        );
    }

    public function testLoadingFromASequenceSkipsEarlierEvents(): void
    {
        $this->store->append(self::MATCH_ID, 0, self::sampleEvents(), 'cmd-1');

        $tail = $this->store->load(self::MATCH_ID, fromSeq: 2);

        self::assertSame([3, 4], array_map(static fn (StoredEvent $e): int => $e->seq, $tail));
    }

    public function testTheSameClientCommandIdCannotBeAppliedTwice(): void
    {
        $this->store->append(self::MATCH_ID, 0, [self::aRejection()], 'cmd-1');

        $this->expectException(DuplicateCommand::class);

        $this->store->append(self::MATCH_ID, 1, [self::aRejection()], 'cmd-1');
    }

    /**
     * The unique key on (match_id, seq) doing its job: a second writer working from
     * the same stale sequence number is refused by the database, not by a lock.
     */
    public function testASecondWriterAtTheSameSequenceIsRefused(): void
    {
        $this->store->append(self::MATCH_ID, 0, [self::aRejection()], 'cmd-1');

        $this->expectException(SequenceConflict::class);

        $this->store->append(self::MATCH_ID, 0, [self::aRejection()], 'cmd-2');
    }

    /**
     * Events and the command that produced them must land together or not at all.
     * If a failed append left its command row behind, the retry would be treated as
     * a duplicate and the play would be silently lost.
     */
    public function testAConflictingAppendLeavesNoCommandRowBehind(): void
    {
        $this->store->append(self::MATCH_ID, 0, [self::aRejection()], 'cmd-1');

        try {
            $this->store->append(self::MATCH_ID, 0, [self::aRejection()], 'cmd-2');
            self::fail('Expected a sequence conflict.');
        } catch (SequenceConflict) {
            self::assertNull($this->store->findByClientCommandId(self::MATCH_ID, 'cmd-2'));
            self::assertCount(1, $this->store->load(self::MATCH_ID));
        }
    }

    public function testACommandCanBeLookedUpByItsClientId(): void
    {
        $this->store->append(self::MATCH_ID, 0, [self::aRejection(), self::aTurnEnd()], 'cmd-1');
        $this->store->append(self::MATCH_ID, 2, [self::aRejection()], 'cmd-2');

        $first = $this->store->findByClientCommandId(self::MATCH_ID, 'cmd-1');
        $second = $this->store->findByClientCommandId(self::MATCH_ID, 'cmd-2');

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame([1, 2], array_map(static fn (StoredEvent $e): int => $e->seq, $first));
        self::assertSame([3], array_map(static fn (StoredEvent $e): int => $e->seq, $second));
        self::assertNull($this->store->findByClientCommandId(self::MATCH_ID, 'never-sent'));
    }

    public function testAppendingNothingIsANoOp(): void
    {
        $this->store->append(self::MATCH_ID, 0, [], 'cmd-1');

        self::assertSame([], $this->store->load(self::MATCH_ID));
        self::assertNull($this->store->findByClientCommandId(self::MATCH_ID, 'cmd-1'));
    }

    /** @return list<Event> */
    private static function sampleEvents(): array
    {
        $at = new \DateTimeImmutable('2026-01-15 09:00:01.500000', new \DateTimeZone('UTC'));

        return [
            new CardsPlayed(self::MATCH_ID, $at, 'alice', ['c1', 'c2', 'c3'], '3 + 4', 7, 1500),
            new CardsDrawn(self::MATCH_ID, $at, 'alice', [
                Card::operand('c9', 11),
                Card::operator('c10', Operator::Multiply),
            ]),
            new TargetRevealed(self::MATCH_ID, $at, 18),
            new TurnEnded(self::MATCH_ID, $at, 'alice', 1),
        ];
    }

    private static function aRejection(): EquationRejected
    {
        return new EquationRejected(
            self::MATCH_ID,
            new \DateTimeImmutable('2026-01-15 09:00:02.000000', new \DateTimeZone('UTC')),
            'alice',
            ['c1', 'c2', 'c3'],
            '3 + 4',
            8,
            RejectReason::OffByOne,
            '7',
        );
    }

    private static function aTurnEnd(): TurnEnded
    {
        return new TurnEnded(
            self::MATCH_ID,
            new \DateTimeImmutable('2026-01-15 09:00:02.000000', new \DateTimeZone('UTC')),
            'alice',
            1,
        );
    }
}
