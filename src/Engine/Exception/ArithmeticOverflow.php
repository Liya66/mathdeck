<?php

declare(strict_types=1);

namespace MathDeck\Engine\Exception;

final class ArithmeticOverflow extends \RuntimeException
{
    public static function in(string $operation, int $a, int $b): self
    {
        return new self(sprintf('Integer overflow in %s(%d, %d).', $operation, $a, $b));
    }
}
