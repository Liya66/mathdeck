<?php

declare(strict_types=1);

namespace MathDeck\Identity;

/**
 * An account and the passcode it was created with.
 *
 * The passcode exists in this object and nowhere else — it is hashed on the way
 * into storage, so this is the only moment anyone can read it. It goes back in the
 * response once, and a teacher who loses it issues a new one rather than recovering
 * the old.
 */
final readonly class NewAccount
{
    public function __construct(
        public Account $account,
        public string $passcode,
    ) {
    }
}
