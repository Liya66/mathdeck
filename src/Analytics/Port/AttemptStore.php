<?php

declare(strict_types=1);

namespace MathDeck\Analytics\Port;

use MathDeck\Analytics\Attempt;

interface AttemptStore
{
    /**
     * Idempotent: facts are keyed by (matchId, seq), so re-running the projection
     * rewrites the same rows rather than doubling them. A projector that could not
     * be safely re-run would be a projector nobody dares restart.
     *
     * @param list<Attempt> $attempts
     */
    public function append(array $attempts): void;

    public function count(): int;
}
