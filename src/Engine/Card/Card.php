<?php

declare(strict_types=1);

namespace MathDeck\Engine\Card;

/**
 * A card is either an operand (a whole number) or an operator. Ids are stable for
 * the lifetime of a match so events can reference cards without embedding them.
 */
final readonly class Card implements \Stringable
{
    private function __construct(
        public string $id,
        public CardKind $kind,
        public ?int $value,
        public ?Operator $operator,
    ) {
    }

    public static function operand(string $id, int $value): self
    {
        return new self($id, CardKind::Operand, $value, null);
    }

    public static function operator(string $id, Operator $operator): self
    {
        return new self($id, CardKind::Operator, null, $operator);
    }

    public function isOperand(): bool
    {
        return $this->kind === CardKind::Operand;
    }

    public function isOperator(): bool
    {
        return $this->kind === CardKind::Operator;
    }

    public function operandValue(): int
    {
        if ($this->value === null) {
            throw new \LogicException(sprintf('Card %s is not an operand.', $this->id));
        }

        return $this->value;
    }

    public function operatorValue(): Operator
    {
        if ($this->operator === null) {
            throw new \LogicException(sprintf('Card %s is not an operator.', $this->id));
        }

        return $this->operator;
    }

    /**
     * @return array{id: string, kind: string, value: int|null, operator: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'value' => $this->value,
            'operator' => $this->operator?->value,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = (string) ($data['id'] ?? '');

        return CardKind::from((string) ($data['kind'] ?? '')) === CardKind::Operand
            ? self::operand($id, (int) ($data['value'] ?? 0))
            : self::operator($id, Operator::from((string) ($data['operator'] ?? '')));
    }

    public function __toString(): string
    {
        return $this->isOperand()
            ? (string) $this->value
            : (string) $this->operator?->value;
    }
}
