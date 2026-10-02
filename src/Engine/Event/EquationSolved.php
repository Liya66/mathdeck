<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

final readonly class EquationSolved implements Event
{
    /** @param list<string> $cardIds */
    public function __construct(
        private string $matchId,
        private \DateTimeImmutable $occurredAt,
        public string $playerId,
        public array $cardIds,
        public string $expression,
        public int $target,
        public int $score,
    ) {
    }

    public function matchId(): string
    {
        return $this->matchId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function type(): string
    {
        return 'equation_solved';
    }

    public function payload(): array
    {
        return [
            'playerId' => $this->playerId,
            'cardIds' => $this->cardIds,
            'expression' => $this->expression,
            'target' => $this->target,
            'score' => $this->score,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(string $matchId, \DateTimeImmutable $occurredAt, array $payload): self
    {
        $reader = new Payload($payload);

        return new self(
            $matchId,
            $occurredAt,
            $reader->string('playerId'),
            $reader->stringList('cardIds'),
            $reader->string('expression'),
            $reader->int('target'),
            $reader->int('score'),
        );
    }
}
