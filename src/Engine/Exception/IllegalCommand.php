<?php

declare(strict_types=1);

namespace MathDeck\Engine\Exception;

use MathDeck\Engine\Rule\RejectReason;
use MathDeck\Engine\Rule\Violation;

/**
 * Raised for commands a correct client could not have sent — wrong turn, cards the
 * player does not hold, a finished match. These are protocol faults or tampering,
 * not learner mistakes, so they produce no game event and must not pollute the
 * error-distribution report.
 */
final class IllegalCommand extends \DomainException
{
    private function __construct(public readonly RejectReason $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function because(Violation $violation): self
    {
        return new self($violation->reason, $violation->message);
    }
}
