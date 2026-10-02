<?php

declare(strict_types=1);

namespace MathDeck\Identity\Exception;

final class TooManyAttempts extends \RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many sign-in attempts. Wait a moment and try again.');
    }
}
