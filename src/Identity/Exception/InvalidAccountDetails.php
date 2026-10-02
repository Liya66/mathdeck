<?php

declare(strict_types=1);

namespace MathDeck\Identity\Exception;

final class InvalidAccountDetails extends \RuntimeException
{
    public static function because(string $detail): self
    {
        return new self($detail);
    }
}
