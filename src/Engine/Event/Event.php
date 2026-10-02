<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

/**
 * Events are the source of truth: state is the fold of the log, and the telemetry
 * the analytics layer projects is this same stream. Two consequences worth holding
 * on to — every event must be self-contained enough to replay without consulting
 * its neighbours, and a shipped event's payload shape is a contract.
 */
interface Event
{
    public function matchId(): string;

    public function occurredAt(): \DateTimeImmutable;

    /** Stable wire name, persisted in the log. Never rename one in place. */
    public function type(): string;

    /** @return array<string, mixed> */
    public function payload(): array;
}
