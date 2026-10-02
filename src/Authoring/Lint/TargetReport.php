<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Lint;

/**
 * Whether one target can actually be made from this deck's cards.
 *
 * `reachable` is deliberately three-valued. The search is exhaustive for ordinary
 * decks, but a very large operand pool can exhaust its budget — and a deck must
 * never be rejected on the strength of a search that gave up. Unknown becomes a
 * warning; only a completed search that found nothing becomes an error.
 */
final readonly class TargetReport
{
    public function __construct(
        public int $target,
        public ?bool $reachable,
        public ?int $shortestCards,
        public int $simpleSolutions,
        public ?string $example,
    ) {
    }

    public static function unknown(int $target): self
    {
        return new self($target, null, null, 0, null);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'reachable' => $this->reachable,
            'shortestCards' => $this->shortestCards,
            'simpleSolutions' => $this->simpleSolutions,
            'example' => $this->example,
        ];
    }
}
