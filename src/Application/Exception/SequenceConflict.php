<?php

declare(strict_types=1);

namespace MathDeck\Application\Exception;

/**
 * Another writer claimed a sequence number in the range we tried to append.
 *
 * This is the whole concurrency control: a unique index on (match_id, seq) means
 * the loser of a race finds out at insert time, reloads and tries again. No locks,
 * and correct even if the cache has evicted everything.
 */
final class SequenceConflict extends \RuntimeException
{
    public static function at(string $matchId, int $seq): self
    {
        return new self(sprintf('Sequence %d of match %s is already taken.', $seq, $matchId));
    }
}
