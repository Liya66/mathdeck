<?php

declare(strict_types=1);

namespace MathDeck\Tests\Integration;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Authoring\DeckStatus;
use MathDeck\Authoring\DeckVersion;
use MathDeck\Infrastructure\Deck\PublishedDeckCatalog;
use MathDeck\Infrastructure\Mysql\MysqlDeckStore;

final class MysqlDeckStoreTest extends MysqlTestCase
{
    private MysqlDeckStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->exec("DELETE FROM deck_versions WHERE deck_id <> 'starter'");
        $this->store = new MysqlDeckStore($this->connection);
    }

    public function testTheSeededStarterDeckSurvivesMigration(): void
    {
        $starter = $this->store->find('starter@1');

        self::assertNotNull($starter);
        self::assertSame(DeckStatus::Published, $starter->status);
        self::assertEquals(
            DeckDocument::starter()->toArray(),
            $starter->document->toArray(),
            'The migration and the in-memory seed must agree, or tests pass against a different deck.',
        );
    }

    public function testADefinitionSurvivesTheJsonColumnIntact(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['name'] = 'Negatives and thirds';
        $definition['operands']['values'] = [-3, 0, 7, 11];
        $definition['play']['requireIntegerResult'] = false;

        $this->store->save(self::draft('numbers', 1, $definition));

        $loaded = $this->store->find('numbers@1');

        self::assertNotNull($loaded);

        // assertEquals, not assertSame: MySQL's JSON type stores objects in its own
        // normalised key order, so a definition comes back with its keys shuffled.
        // The content is intact and JSON objects are unordered by definition — but
        // any code that compared serialised deck JSON byte-for-byte would be wrong.
        self::assertEquals($definition, $loaded->document->toArray());

        // Array order, unlike object key order, is preserved — and has to be.
        self::assertSame([-3, 0, 7, 11], $loaded->document->operandValues());
        self::assertFalse($loaded->document->play('requireIntegerResult', true));
    }

    public function testVersionsCountUpPerDeck(): void
    {
        self::assertSame(1, $this->store->nextVersion('fresh'));

        $this->store->save(self::draft('fresh', 1));
        $this->store->save(self::draft('fresh', 2));

        self::assertSame(3, $this->store->nextVersion('fresh'));
        self::assertSame(2, $this->store->nextVersion('starter'), 'Other decks are unaffected.');
    }

    public function testPublishingIsPersistedWithItsTimestamp(): void
    {
        $at = new \DateTimeImmutable('2026-03-04 10:11:12.131415', new \DateTimeZone('UTC'));
        $this->store->save(self::draft('numbers', 1)->published($at));

        $loaded = $this->store->find('numbers@1');

        self::assertNotNull($loaded);
        self::assertSame(DeckStatus::Published, $loaded->status);
        self::assertSame('2026-03-04 10:11:12.131415', $loaded->publishedAt?->format('Y-m-d H:i:s.u'));
    }

    /** A draft must not be playable, whatever the storage layer says. */
    public function testOnlyPublishedVersionsReachTheMatchSide(): void
    {
        $this->store->save(self::draft('numbers', 1));
        $catalog = new PublishedDeckCatalog($this->store);

        self::assertSame(range(0, 12), $catalog->rulesFor('starter@1')->operandPool);

        $this->expectException(\MathDeck\Application\Exception\DeckNotFound::class);

        $catalog->rulesFor('numbers@1');
    }

    /** @param array<string, mixed>|null $definition */
    private static function draft(string $deckId, int $version, ?array $definition = null): DeckVersion
    {
        return new DeckVersion(
            deckId: $deckId,
            version: $version,
            authorId: 'miss-lee',
            status: DeckStatus::Draft,
            document: DeckDocument::fromArray($definition ?? DeckDocument::starter()->toArray()),
            updatedAt: new \DateTimeImmutable('2026-03-01 09:00:00', new \DateTimeZone('UTC')),
        );
    }
}
