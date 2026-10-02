<?php

declare(strict_types=1);

namespace MathDeck\Engine\Exception;

use MathDeck\Engine\Rule\RejectReason;

final class MalformedExpression extends \DomainException
{
    private function __construct(public readonly RejectReason $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function because(RejectReason $reason, string $message): self
    {
        return new self($reason, $message);
    }
}
