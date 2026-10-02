<?php

declare(strict_types=1);

namespace MathDeck\Application\Exception;

/**
 * An event type in the log that this deployment cannot read. Never skip one: a
 * partial fold is a wrong match state that looks like a right one.
 */
final class UnknownEventType extends \RuntimeException
{
    public static function named(string $type): self
    {
        return new self(sprintf('No mapping registered for event type "%s".', $type));
    }
}
