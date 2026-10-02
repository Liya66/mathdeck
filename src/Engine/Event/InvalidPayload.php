<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

/**
 * A stored event could not be read back. This is a corruption or a deployment
 * mistake, never a user error: fail loudly rather than reconstructing a match from
 * a payload nobody understands.
 */
final class InvalidPayload extends \RuntimeException
{
    public static function missing(string $key): self
    {
        return new self(sprintf('Event payload is missing "%s".', $key));
    }

    public static function wrongType(string $key, string $expected): self
    {
        return new self(sprintf('Event payload key "%s" is not %s.', $key, $expected));
    }
}
