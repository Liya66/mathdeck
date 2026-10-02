<?php

declare(strict_types=1);

namespace MathDeck\Infrastructure\InMemory;

use MathDeck\Analytics\Attempt;
use MathDeck\Analytics\Percentile;
use MathDeck\Analytics\Port\AttemptStore;
use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Analytics\ReportFilter;

/**
 * The reference implementation of the report contract.
 *
 * Deliberately written first and kept readable: the MySQL version has to produce
 * the same numbers, and it is much easier to argue about a SQL window function when
 * the definition it must match is fifteen lines of plain PHP.
 */
final class InMemoryAttemptStore implements AttemptStore, ReportQueries
{
    /** @var array<string, Attempt> */
    private array $attempts = [];

    public function append(array $attempts): void
    {
        foreach ($attempts as $attempt) {
            $this->attempts[$attempt->id()] = $attempt;
        }
    }

    public function count(): int
    {
        return count($this->attempts);
    }

    public function overview(ReportFilter $filter): array
    {
        $rows = $this->matching($filter);
        $solved = array_filter($rows, static fn (Attempt $a): bool => $a->solved);

        return [
            'attempts' => count($rows),
            'solved' => count($solved),
            'accuracy' => self::share(count($solved), count($rows)),
            'students' => count(array_unique(array_map(static fn (Attempt $a): string => $a->studentKey, $rows))),
            'matches' => count(array_unique(array_map(static fn (Attempt $a): string => $a->matchId, $rows))),
            'totalScore' => array_sum(array_map(static fn (Attempt $a): int => $a->score, $rows)),
            'medianLatencyMs' => Percentile::median(self::latencies($rows)),
        ];
    }

    public function progression(ReportFilter $filter): array
    {
        $byStudent = [];

        foreach ($this->matching($filter) as $attempt) {
            $byStudent[$attempt->studentKey][] = $attempt;
        }

        ksort($byStudent);

        $rows = [];

        foreach ($byStudent as $studentKey => $attempts) {
            $solved = array_filter($attempts, static fn (Attempt $a): bool => $a->solved);
            $times = array_map(static fn (Attempt $a): \DateTimeImmutable => $a->occurredAt, $attempts);

            $rows[] = [
                'studentKey' => (string) $studentKey,
                'attempts' => count($attempts),
                'solved' => count($solved),
                'accuracy' => self::share(count($solved), count($attempts)),
                'totalScore' => array_sum(array_map(static fn (Attempt $a): int => $a->score, $attempts)),
                'medianLatencyMs' => Percentile::median(self::latencies($attempts)),
                'firstSeen' => min($times)->format('Y-m-d\TH:i:s.up'),
                'lastSeen' => max($times)->format('Y-m-d\TH:i:s.up'),
            ];
        }

        return $rows;
    }

    public function errorDistribution(ReportFilter $filter): array
    {
        $errors = [];
        $byReason = [];

        foreach ($this->matching($filter) as $attempt) {
            if ($attempt->reason === null) {
                continue;
            }

            $errors[] = $attempt;
            $byReason[$attempt->reason->value][] = $attempt;
        }

        $rows = [];

        foreach ($byReason as $reason => $attempts) {
            $rows[] = [
                'reason' => (string) $reason,
                'count' => count($attempts),
                // Share of mistakes, not of all attempts: a teacher reading this is
                // asking "when they get it wrong, what goes wrong?"
                'share' => self::share(count($attempts), count($errors)),
                'students' => count(array_unique(array_map(static fn (Attempt $a): string => $a->studentKey, $attempts))),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['count'], $a['reason']] <=> [$a['count'], $b['reason']]);

        return $rows;
    }

    public function latency(ReportFilter $filter): array
    {
        $byBucket = [];

        foreach ($this->matching($filter) as $attempt) {
            $byBucket[$attempt->solved ? 'solved' : $attempt->reason->value ?? 'unknown'][] = $attempt;
        }

        $rows = [];

        foreach ($byBucket as $bucket => $attempts) {
            $latencies = self::latencies($attempts);

            $rows[] = [
                'bucket' => (string) $bucket,
                'count' => count($attempts),
                'medianLatencyMs' => Percentile::median($latencies) ?? 0,
                'p90LatencyMs' => Percentile::p90($latencies) ?? 0,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['count'], $a['bucket']] <=> [$a['count'], $b['bucket']]);

        return $rows;
    }

    /** @return list<Attempt> */
    private function matching(ReportFilter $filter): array
    {
        return array_values(array_filter(
            array_values($this->attempts),
            static fn (Attempt $attempt): bool => $filter->matches($attempt),
        ));
    }

    /**
     * @param list<Attempt> $attempts
     *
     * @return list<int>
     */
    private static function latencies(array $attempts): array
    {
        $latencies = array_map(static fn (Attempt $a): int => $a->latencyMs, $attempts);
        sort($latencies);

        return $latencies;
    }

    private static function share(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round($part / $whole, 4);
    }
}
