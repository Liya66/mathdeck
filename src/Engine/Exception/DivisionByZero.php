<?php

declare(strict_types=1);

namespace MathDeck\Engine\Exception;

final class DivisionByZero extends \DomainException
{
    public static function create(): self
    {
        return new self('Division by zero.');
    }
}
