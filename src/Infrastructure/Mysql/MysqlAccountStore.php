<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Identity\Account;
use MathDeck\Identity\Port\AccountStore;
use MathDeck\Identity\Role;

final readonly class MysqlAccountStore implements AccountStore
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private \PDO $connection)
    {
    }

    public function save(Account $account): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO accounts (player_id, display_name, role, password_hash, analytics_key, created_at)
             VALUES (:player_id, :display_name, :role, :password_hash, :analytics_key, :created_at)
             ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name), role = VALUES(role),
                password_hash = VALUES(password_hash)',
        );

        $statement->execute([
            'player_id' => $account->playerId,
            'display_name' => $account->displayName,
            'role' => $account->role->value,
            'password_hash' => $account->passwordHash,
            'analytics_key' => $account->analyticsKey,
            'created_at' => $account->createdAt->format(self::TIMESTAMP_FORMAT),
        ]);
    }

    public function find(string $playerId): ?Account
    {
        $statement = $this->connection->prepare('SELECT * FROM accounts WHERE player_id = :player_id');
        $statement->execute(['player_id' => $playerId]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): Account
    {
        return new Account(
            playerId: (string) $row['player_id'],
            displayName: (string) $row['display_name'],
            role: Role::from((string) $row['role']),
            passwordHash: (string) $row['password_hash'],
            analyticsKey: (string) $row['analytics_key'],
            createdAt: new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
        );
    }

    public function all(): array
    {
        $statement = $this->connection->query('SELECT * FROM accounts ORDER BY player_id ASC');
        $accounts = [];

        /** @var array<string, mixed> $row */
        foreach ($statement === false ? [] : $statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $accounts[] = self::hydrate($row);
        }

        return $accounts;
    }

    public function displayNamesFor(array $analyticsKeys): array
    {
        if ($analyticsKeys === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($analyticsKeys as $index => $key) {
            $placeholders[] = ':key_' . $index;
            $params['key_' . $index] = $key;
        }

        $statement = $this->connection->prepare(sprintf(
            'SELECT analytics_key, display_name FROM accounts WHERE analytics_key IN (%s)',
            implode(', ', $placeholders),
        ));
        $statement->execute($params);

        $names = [];

        /** @var array<string, mixed> $row */
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $names[(string) $row['analytics_key']] = (string) $row['display_name'];
        }

        return $names;
    }

    public function analyticsKeyFor(string $playerId): ?string
    {
        $statement = $this->connection->prepare(
            'SELECT analytics_key FROM accounts WHERE player_id = :player_id',
        );
        $statement->execute(['player_id' => $playerId]);

        $key = $statement->fetchColumn();

        return $key === false ? null : (string) $key;
    }
}
