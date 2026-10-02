<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

final readonly class Connection
{
    public static function fromEnvironment(): ?\PDO
    {
        $dsn = getenv('MATCHDECK_DSN');

        if (!is_string($dsn) || $dsn === '') {
            return null;
        }

        $user = getenv('MATCHDECK_DB_USER');
        $password = getenv('MATCHDECK_DB_PASSWORD');

        return self::open(
            $dsn,
            is_string($user) ? $user : 'mathdeck',
            is_string($password) ? $password : 'mathdeck',
        );
    }

    public static function open(string $dsn, string $user, string $password): \PDO
    {
        return new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            // Real prepared statements, so a duplicate key surfaces as an exception
            // from the statement that caused it rather than at fetch time.
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
