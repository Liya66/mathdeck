<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\Mysql;

use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Analytics\ReportFilter;

/**
 * The reports, aggregated by the database.
 *
 * Every number here must match InMemoryAttemptStore exactly — a shared contract
 * test holds both to it. The window functions look heavier than the PHP they
 * mirror, but the alternative is pulling every attempt into memory to compute a
 * median, which is the thing this phase exists to avoid.
 */
final readonly class MysqlReportQueries implements ReportQueries
{
    public function __construct(private \PDO $connection)
    {
    }

    public function overview(ReportFilter $filter): array
    {
        [$where, $params] = self::where($filter);

        $row = $this->one(
            "SELECT COUNT(*) AS attempts,
                    COALESCE(SUM(solved), 0) AS solved,
                    COUNT(DISTINCT student_key) AS students,
                    COUNT(DISTINCT match_id) AS matches,
                    COALESCE(SUM(score), 0) AS total_score
             FROM fact_attempt WHERE {$where}",
            $params,
        );

        $attempts = (int) ($row['attempts'] ?? 0);
        $solved = (int) ($row['solved'] ?? 0);

        return [
            'attempts' => $attempts,
            'solved' => $solved,
            'accuracy' => self::share($solved, $attempts),
            'students' => (int) ($row['students'] ?? 0),
            'matches' => (int) ($row['matches'] ?? 0),
            'totalScore' => (int) ($row['total_score'] ?? 0),
            'medianLatencyMs' => $this->medianLatency($where, $params),
        ];
    }

    public function progression(ReportFilter $filter): array
    {
        [$where, $params] = self::where($filter);

        $medians = [];

        foreach ($this->all(
            "SELECT student_key, latency_ms FROM (
                 SELECT student_key, latency_ms,
                        ROW_NUMBER() OVER (PARTITION BY student_key ORDER BY latency_ms) AS rn,
                        COUNT(*) OVER (PARTITION BY student_key) AS cnt
                 FROM fact_attempt WHERE {$where}
             ) ranked WHERE rn = ((cnt - 1) DIV 2) + 1",
            $params,
        ) as $row) {
            $medians[(string) $row['student_key']] = (int) $row['latency_ms'];
        }

        $rows = [];

        foreach ($this->all(
            "SELECT student_key, COUNT(*) AS attempts, COALESCE(SUM(solved), 0) AS solved,
                    COALESCE(SUM(score), 0) AS total_score,
                    MIN(occurred_at) AS first_seen, MAX(occurred_at) AS last_seen
             FROM fact_attempt WHERE {$where}
             GROUP BY student_key ORDER BY student_key ASC",
            $params,
        ) as $row) {
            $studentKey = (string) $row['student_key'];
            $attempts = (int) $row['attempts'];
            $solved = (int) $row['solved'];

            $rows[] = [
                'studentKey' => $studentKey,
                'attempts' => $attempts,
                'solved' => $solved,
                'accuracy' => self::share($solved, $attempts),
                'totalScore' => (int) $row['total_score'],
                'medianLatencyMs' => $medians[$studentKey] ?? null,
                'firstSeen' => self::timestamp((string) $row['first_seen']),
                'lastSeen' => self::timestamp((string) $row['last_seen']),
            ];
        }

        return $rows;
    }

    public function errorDistribution(ReportFilter $filter): array
    {
        [$where, $params] = self::where($filter);

        $rows = $this->all(
            "SELECT reason_code, COUNT(*) AS occurrences, COUNT(DISTINCT student_key) AS students
             FROM fact_attempt WHERE {$where} AND reason_code IS NOT NULL
             GROUP BY reason_code ORDER BY occurrences DESC, reason_code ASC",
            $params,
        );

        $total = array_sum(array_map(static fn (array $row): int => (int) $row['occurrences'], $rows));

        return array_map(
            static fn (array $row): array => [
                'reason' => (string) $row['reason_code'],
                'count' => (int) $row['occurrences'],
                'share' => self::share((int) $row['occurrences'], $total),
                'students' => (int) $row['students'],
            ],
            $rows,
        );
    }

    public function latency(ReportFilter $filter): array
    {
        [$where, $params] = self::where($filter);

        $rows = $this->all(
            "SELECT bucket, COUNT(*) AS occurrences,
                    MAX(CASE WHEN rn = median_rn THEN latency_ms END) AS median_ms,
                    MAX(CASE WHEN rn = p90_rn THEN latency_ms END) AS p90_ms
             FROM (
                 SELECT bucket, latency_ms,
                        ROW_NUMBER() OVER (PARTITION BY bucket ORDER BY latency_ms) AS rn,
                        ((COUNT(*) OVER (PARTITION BY bucket) - 1) DIV 2) + 1 AS median_rn,
                        GREATEST(1, CEIL(0.9 * COUNT(*) OVER (PARTITION BY bucket))) AS p90_rn
                 FROM (
                     SELECT CASE WHEN solved = 1 THEN 'solved'
                                 ELSE COALESCE(reason_code, 'unknown') END AS bucket,
                            latency_ms
                     FROM fact_attempt WHERE {$where}
                 ) bucketed
             ) ranked
             GROUP BY bucket ORDER BY occurrences DESC, bucket ASC",
            $params,
        );

        return array_map(
            static fn (array $row): array => [
                'bucket' => (string) $row['bucket'],
                'count' => (int) $row['occurrences'],
                'medianLatencyMs' => (int) $row['median_ms'],
                'p90LatencyMs' => (int) $row['p90_ms'],
            ],
            $rows,
        );
    }

    /** @param array<string, mixed> $params */
    private function medianLatency(string $where, array $params): ?int
    {
        $row = $this->one(
            "SELECT latency_ms FROM (
                 SELECT latency_ms,
                        ROW_NUMBER() OVER (ORDER BY latency_ms) AS rn,
                        COUNT(*) OVER () AS cnt
                 FROM fact_attempt WHERE {$where}
             ) ranked WHERE rn = ((cnt - 1) DIV 2) + 1",
            $params,
        );

        return isset($row['latency_ms']) ? (int) $row['latency_ms'] : null;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function where(ReportFilter $filter): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        if ($filter->deckVersionId !== null) {
            $clauses[] = 'deck_version_id = :deck_version_id';
            $params['deck_version_id'] = $filter->deckVersionId;
        }

        if ($filter->studentKeys !== []) {
            $placeholders = [];

            foreach ($filter->studentKeys as $index => $studentKey) {
                $placeholders[] = ':student_' . $index;
                $params['student_' . $index] = $studentKey;
            }

            $clauses[] = 'student_key IN (' . implode(', ', $placeholders) . ')';
        }

        if ($filter->from !== null) {
            $clauses[] = 'occurred_at >= :from';
            $params['from'] = $filter->from->format('Y-m-d H:i:s.u');
        }

        if ($filter->to !== null) {
            $clauses[] = 'occurred_at <= :to';
            $params['to'] = $filter->to->format('Y-m-d H:i:s.u');
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function all(string $sql, array $params): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $rows = [];

        /** @var array<string, mixed> $row */
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function one(string $sql, array $params): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private static function timestamp(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.up');
    }

    private static function share(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole, 4);
    }
}
