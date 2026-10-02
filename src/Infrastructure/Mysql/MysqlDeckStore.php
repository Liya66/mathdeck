<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Authoring\DeckStatus;
use MathDeck\Authoring\DeckVersion;
use MathDeck\Authoring\Port\DeckStore;

final readonly class MysqlDeckStore implements DeckStore
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private \PDO $connection)
    {
    }

    public function save(DeckVersion $version): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO deck_versions
                (deck_version_id, deck_id, version, name, author_id, status, definition, updated_at, published_at)
             VALUES
                (:id, :deck_id, :version, :name, :author_id, :status, :definition, :updated_at, :published_at)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                status = VALUES(status),
                definition = VALUES(definition),
                updated_at = VALUES(updated_at),
                published_at = VALUES(published_at)',
        );

        $statement->execute([
            'id' => $version->id(),
            'deck_id' => $version->deckId,
            'version' => $version->version,
            'name' => $version->name(),
            'author_id' => $version->authorId,
            'status' => $version->status->value,
            'definition' => json_encode($version->document->toArray(), JSON_THROW_ON_ERROR),
            'updated_at' => $version->updatedAt->format(self::TIMESTAMP_FORMAT),
            'published_at' => $version->publishedAt?->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    public function find(string $deckVersionId): ?DeckVersion
    {
        $statement = $this->connection->prepare('SELECT * FROM deck_versions WHERE deck_version_id = :id');
        $statement->execute(['id' => $deckVersionId]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrate($row) : null;
    }

    public function all(): array
    {
        $statement = $this->connection->query('SELECT * FROM deck_versions ORDER BY updated_at DESC');

        $versions = [];

        /** @var array<string, mixed> $row */
        foreach ($statement === false ? [] : $statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $versions[] = self::hydrate($row);
        }

        return $versions;
    }

    public function nextVersion(string $deckId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM deck_versions WHERE deck_id = :deck_id',
        );
        $statement->execute(['deck_id' => $deckId]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): DeckVersion
    {
        $definition = json_decode((string) $row['definition'], associative: true, flags: JSON_THROW_ON_ERROR);

        return new DeckVersion(
            deckId: (string) $row['deck_id'],
            version: (int) $row['version'],
            authorId: (string) $row['author_id'],
            status: DeckStatus::from((string) $row['status']),
            document: DeckDocument::fromArray(is_array($definition) ? $definition : []),
            updatedAt: new \DateTimeImmutable((string) $row['updated_at'], new \DateTimeZone('UTC')),
            publishedAt: $row['published_at'] === null
                ? null
                : new \DateTimeImmutable((string) $row['published_at'], new \DateTimeZone('UTC')),
        );
    }
}
