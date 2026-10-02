<?php

declare(strict_types=1);

namespace MathDeck\Identity;

use MathDeck\Engine\Clock;
use MathDeck\Identity\Exception\TooManyAttempts;
use MathDeck\Identity\Port\SignInAttempts;

/**
 * Slows down passcode guessing.
 *
 * Children's passcodes are short and memorable by design, which is the right call
 * for a classroom and the wrong one for an unthrottled endpoint — `play-1234` falls
 * to a few thousand guesses. This is what makes a short passcode defensible.
 *
 * Two buckets, and a breach of either refuses:
 *
 *   - **per account**, which stops someone grinding away at one child's passcode;
 *   - **per client address**, which stops the same attacker spraying one guess
 *     across every account instead.
 *
 * Only failures count, and a success clears the account's bucket — otherwise a busy
 * classroom signing in normally would lock itself out.
 */
final readonly class SignInThrottle
{
    public function __construct(
        private SignInAttempts $attempts,
        private Clock $clock,
        private int $maxPerAccount = 10,
        private int $maxPerAddress = 30,
        private int $windowSeconds = 300,
    ) {
    }

    /** @throws TooManyAttempts */
    public function check(string $playerId, ?string $clientAddress): void
    {
        $since = $this->windowStart();

        if ($this->attempts->failuresSince(self::accountKey($playerId), $since) >= $this->maxPerAccount) {
            throw new TooManyAttempts($this->windowSeconds);
        }

        if ($clientAddress !== null
            && $this->attempts->failuresSince(self::addressKey($clientAddress), $since) >= $this->maxPerAddress) {
            throw new TooManyAttempts($this->windowSeconds);
        }
    }

    public function recordFailure(string $playerId, ?string $clientAddress): void
    {
        $now = $this->clock->now();

        $this->attempts->recordFailure(self::accountKey($playerId), $now);

        if ($clientAddress !== null) {
            $this->attempts->recordFailure(self::addressKey($clientAddress), $now);
        }
    }

    /**
     * Clears the account bucket only. The address bucket survives on purpose: an
     * attacker who guesses one account correctly must not thereby reset the budget
     * they were using against all the others.
     */
    public function recordSuccess(string $playerId): void
    {
        $this->attempts->clear(self::accountKey($playerId));
    }

    private function windowStart(): \DateTimeImmutable
    {
        return $this->clock->now()->modify(sprintf('-%d seconds', $this->windowSeconds));
    }

    private static function accountKey(string $playerId): string
    {
        return 'account:' . $playerId;
    }

    private static function addressKey(string $clientAddress): string
    {
        return 'address:' . $clientAddress;
    }
}
