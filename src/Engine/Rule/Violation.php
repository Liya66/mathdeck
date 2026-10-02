<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

final readonly class Violation
{
    private function __construct(
        public RejectReason $reason,
        public Severity $severity,
        public string $message,
    ) {
    }

    public static function protocol(RejectReason $reason, string $message): self
    {
        return new self($reason, Severity::Protocol, $message);
    }

    public static function gameplay(RejectReason $reason, string $message): self
    {
        return new self($reason, Severity::Gameplay, $message);
    }
}
