<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Application\MatchRecord;
use MathDeck\Application\Port\MatchStore;
use MathDeck\Engine\State\DeckRules;

final readonly class MysqlMatchStore implements MatchStore
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private \PDO $connection)
    {
    }

    public function save(MatchRecord $record): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO matches (match_id, deck_version_id, seed, player_ids, rules, created_at)
             VALUES (:match_id, :deck_version_id, :seed, :player_ids, :rules, :created_at)',
        );

        $statement->execute([
            'match_id' => $record->matchId,
            'deck_version_id' => $record->deckVersionId,
            'seed' => $record->seed,
            'player_ids' => json_encode($record->playerIds, JSON_THROW_ON_ERROR),
            'rules' => json_encode($record->rules->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => $record->createdAt->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    public function find(string $matchId): ?MatchRecord
    {
        $statement = $this->connection->prepare(
            'SELECT match_id, deck_version_id, seed, player_ids, rules, created_at
             FROM matches WHERE match_id = :match_id',
        );
        $statement->execute(['match_id' => $matchId]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return new MatchRecord(
            matchId: (string) $row['match_id'],
            deckVersionId: (string) $row['deck_version_id'],
            seed: (int) $row['seed'],
            playerIds: self::decodeStringList((string) $row['player_ids']),
            rules: DeckRules::fromArray(self::decodeMap((string) $row['rules'])),
            createdAt: new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
        );
    }

    public function allMatchIds(): array
    {
        $statement = $this->connection->query('SELECT match_id FROM matches ORDER BY created_at ASC');
        $ids = [];

        foreach ($statement === false ? [] : $statement->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $ids[] = (string) $id;
        }

        return $ids;
    }

    /** @return list<string> */
    private static function decodeStringList(string $json): array
    {
        $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        $values = [];

        foreach (is_array($decoded) ? $decoded : [] as $value) {
            $values[] = (string) $value;
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private static function decodeMap(string $json): array
    {
        $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> */
        return is_array($decoded) ? $decoded : [];
    }
}
