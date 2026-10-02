<?php

declare(strict_types=1);

namespace MathDeck\Engine\Command;

/**
 * Clients send intent, never state. There is no field here the server would have
 * to take on trust: no score, no result, no board contents.
 *
 * clientCommandId is the idempotency key. A retry over flaky school wifi must
 * return the original events rather than playing the cards twice.
 */
interface Command
{
    public function matchId(): string;

    public function playerId(): string;

    public function clientCommandId(): string;
}
