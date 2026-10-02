<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Analytics\Attempt;
use MathDeck\Analytics\Port\AttemptStore;

final readonly class MysqlAttemptStore implements AttemptStore
{
    public function __construct(private \PDO $connection)
    {
    }

    public function append(array $attempts): void
    {
        if ($attempts === []) {
            return;
        }

        // Upsert on (match_id, seq): the worker can be restarted, re-run over old
        // matches, or run twice at once, and the facts come out the same.
        $statement = $this->connection->prepare(
            'INSERT INTO fact_attempt
                (match_id, seq, student_key, deck_version_id, target, expression, card_count,
                 solved, reason_code, score, latency_ms, occurred_at)
             VALUES
                (:match_id, :seq, :student_key, :deck_version_id, :target, :expression, :card_count,
                 :solved, :reason_code, :score, :latency_ms, :occurred_at)
             ON DUPLICATE KEY UPDATE
                student_key = VALUES(student_key), deck_version_id = VALUES(deck_version_id),
                target = VALUES(target), expression = VALUES(expression), card_count = VALUES(card_count),
                solved = VALUES(solved), reason_code = VALUES(reason_code), score = VALUES(score),
                latency_ms = VALUES(latency_ms), occurred_at = VALUES(occurred_at)',
        );

        $this->connection->beginTransaction();

        try {
            foreach ($attempts as $attempt) {
                $statement->execute([
                    'match_id' => $attempt->matchId,
                    'seq' => $attempt->seq,
                    'student_key' => $attempt->studentKey,
                    'deck_version_id' => $attempt->deckVersionId,
                    'target' => $attempt->target,
                    'expression' => $attempt->expression,
                    'card_count' => $attempt->cardCount,
                    'solved' => $attempt->solved ? 1 : 0,
                    'reason_code' => $attempt->reason?->value,
                    'score' => $attempt->score,
                    'latency_ms' => $attempt->latencyMs,
                    'occurred_at' => $attempt->occurredAt->format('Y-m-d H:i:s.u'),
                ]);
            }

            $this->connection->commit();
        } catch (\Throwable $failure) {
            $this->connection->rollBack();

            throw $failure;
        }
    }

    public function count(): int
    {
        $statement = $this->connection->query('SELECT COUNT(*) FROM fact_attempt');

        return $statement === false ? 0 : (int) $statement->fetchColumn();
    }
}
