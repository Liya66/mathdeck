<?php

declare(strict_types=1);

namespace MathDeck\Http\Exception;

final class Forbidden extends \RuntimeException
{
    public static function because(string $detail): self
    {
        return new self($detail);
    }
}
