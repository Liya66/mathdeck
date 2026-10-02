<?php

declare(strict_types=1);

namespace MathDeck\Http\Exception;

final class BadRequest extends \RuntimeException
{
    public static function because(string $detail): self
    {
        return new self($detail);
    }
}
