<?php

declare(strict_types=1);

namespace MathDeck\Analytics\Port;

use MathDeck\Analytics\ReportFilter;

/**
 * Everything the teacher dashboard asks for.
 *
 * Two implementations exist — one aggregating in PHP for tests, one in SQL for
 * production — and a single contract test runs against both. Reports are the place
 * where a subtly different answer from two code paths would be hardest to notice
 * and worst to act on.
 */
interface ReportQueries
{
    /**
     * @return array{
     *     attempts: int, solved: int, accuracy: float, students: int,
     *     matches: int, totalScore: int, medianLatencyMs: int|null
     * }
     */
    public function overview(ReportFilter $filter): array;

    /**
     * Per student pseudonym, ordered by key.
     *
     * @return list<array{
     *     studentKey: string, attempts: int, solved: int, accuracy: float,
     *     totalScore: int, medianLatencyMs: int|null, firstSeen: string, lastSeen: string
     * }>
     */
    public function progression(ReportFilter $filter): array;

    /**
     * Misconceptions by frequency, most common first.
     *
     * @return list<array{reason: string, count: int, share: float, students: int}>
     */
    public function errorDistribution(ReportFilter $filter): array;

    /**
     * How long an attempt takes, split by what happened. Slow-and-right and
     * fast-and-wrong are different problems and want different teaching.
     *
     * @return list<array{bucket: string, count: int, medianLatencyMs: int, p90LatencyMs: int}>
     */
    public function latency(ReportFilter $filter): array;
}
