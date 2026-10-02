<?php

declare(strict_types=1);

namespace MathDeck\Authoring;

use MathDeck\Engine\Card\Operator;
use MathDeck\Engine\State\DeckRules;

/**
 * A deck as a teacher authored it, and the translation into what the engine runs.
 *
 * A deck is data. Nothing a teacher can write here needs a deployment, which is the
 * whole point of the format: the engine takes operands, operators, targets and a few
 * knobs, and that is the entire vocabulary.
 */
final readonly class DeckDocument
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function starter(): self
    {
        return self::fromArray([
            'schemaVersion' => 1,
            'name' => 'Starter deck',
            'description' => 'Whole numbers to 12 with all four operations.',
            'operands' => ['values' => range(0, 12), 'copies' => 3],
            'operators' => ['symbols' => ['+', '-', '*', '/'], 'copies' => 8],
            'targets' => range(1, 24),
            'play' => [
                'handSize' => 7,
                'minimumCards' => 3,
                'maximumCards' => 7,
                'targetsPerMatch' => 10,
                'requireIntegerResult' => true,
                'baseScore' => 10,
            ],
        ]);
    }

    public function name(): string
    {
        return (string) ($this->data['name'] ?? '');
    }

    /** @return list<int> */
    public function operandValues(): array
    {
        return $this->intList($this->data['operands']['values'] ?? []);
    }

    public function operandCopies(): int
    {
        return (int) ($this->data['operands']['copies'] ?? 1);
    }

    /** @return list<string> */
    public function operatorSymbols(): array
    {
        $symbols = [];

        foreach (is_array($this->data['operators']['symbols'] ?? null) ? $this->data['operators']['symbols'] : [] as $symbol) {
            $symbols[] = (string) $symbol;
        }

        return $symbols;
    }

    public function operatorCopies(): int
    {
        return (int) ($this->data['operators']['copies'] ?? 1);
    }

    /** @return list<int> */
    public function targets(): array
    {
        return $this->intList($this->data['targets'] ?? []);
    }

    public function play(string $key, int|bool $default): int|bool
    {
        $value = $this->data['play'][$key] ?? $default;

        return is_bool($default) ? (bool) $value : (int) $value;
    }

    public function minimumOperands(): int
    {
        return intdiv((int) $this->play('minimumCards', 3) + 1, 2);
    }

    public function maximumOperands(): int
    {
        return intdiv((int) $this->play('maximumCards', 7) + 1, 2);
    }

    /**
     * The engine's view of this deck. A match copies the result at creation and
     * never looks at the deck again.
     */
    public function toDeckRules(): DeckRules
    {
        return new DeckRules(
            operandPool: $this->operandValues(),
            operators: array_map(
                static fn (string $symbol): Operator => Operator::from($symbol),
                $this->operatorSymbols(),
            ),
            targetPool: $this->targets(),
            handSize: (int) $this->play('handSize', 7),
            minimumCards: (int) $this->play('minimumCards', 3),
            maximumCards: (int) $this->play('maximumCards', 7),
            targetsPerMatch: (int) $this->play('targetsPerMatch', 10),
            requireIntegerResult: (bool) $this->play('requireIntegerResult', true),
            baseScore: (int) $this->play('baseScore', 10),
            operandCopies: $this->operandCopies(),
            operatorCopies: $this->operatorCopies(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @return list<int> */
    private function intList(mixed $values): array
    {
        $list = [];

        foreach (is_array($values) ? $values : [] as $value) {
            $list[] = (int) $value;
        }

        return $list;
    }
}
