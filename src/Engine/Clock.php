<?php

declare(strict_types=1);

namespace MathDeck\Engine;

/**
 * Time is injected so that replaying a match is reproducible. The moment `time()`
 * appears inside the engine, determinism dies quietly.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
