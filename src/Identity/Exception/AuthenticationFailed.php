<?php

declare(strict_types=1);

namespace MathDeck\Identity\Exception;

/**
 * One exception for every way signing in can fail.
 *
 * Deliberately undifferentiated: "no such account" and "wrong passcode" must be
 * indistinguishable from outside, or the endpoint becomes a way to enumerate who
 * has an account.
 */
final class AuthenticationFailed extends \RuntimeException
{
    public static function credentials(): self
    {
        return new self('Those credentials are not valid.');
    }

    public static function token(string $why): self
    {
        return new self($why);
    }
}
