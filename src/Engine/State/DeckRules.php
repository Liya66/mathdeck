<?php

declare(strict_types=1);

namespace MathDeck\Engine\State;

use MathDeck\Engine\Card\Operator;

/**
 * The balance knobs a published deck version fixes. A match stores the deck version
 * it was created from and never re-reads it, so editing a deck cannot rewrite history.
 */
final readonly class DeckRules
{
    /**
     * @param list<int>      $operandPool
     * @param list<Operator> $operators
     * @param list<int>      $targetPool
     */
    public function __construct(
        public array $operandPool,
        public array $operators,
        public array $targetPool,
        public int $handSize = 7,
        public int $minimumCards = 3,
        public int $maximumCards = 7,
        public int $targetsPerMatch = 10,
        public bool $requireIntegerResult = true,
        public int $baseScore = 10,
        public int $operandCopies = 3,
        public int $operatorCopies = 8,
    ) {
    }

    public static function default(): self
    {
        return new self(
            operandPool: range(0, 12),
            operators: Operator::cases(),
            targetPool: range(1, 24),
        );
    }

    /**
     * A match stores its own copy of the rules it was created with, so a later edit
     * to the deck cannot retroactively change how a finished match is replayed.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operandPool' => $this->operandPool,
            'operators' => array_map(static fn (Operator $o): string => $o->value, $this->operators),
            'targetPool' => $this->targetPool,
            'handSize' => $this->handSize,
            'minimumCards' => $this->minimumCards,
            'maximumCards' => $this->maximumCards,
            'targetsPerMatch' => $this->targetsPerMatch,
            'requireIntegerResult' => $this->requireIntegerResult,
            'baseScore' => $this->baseScore,
            'operandCopies' => $this->operandCopies,
            'operatorCopies' => $this->operatorCopies,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $intList = static function (mixed $values): array {
            $list = [];

            foreach (is_array($values) ? $values : [] as $value) {
                $list[] = (int) $value;
            }

            return $list;
        };

        $operators = [];

        foreach (is_array($data['operators'] ?? null) ? $data['operators'] : [] as $operator) {
            $operators[] = Operator::from((string) $operator);
        }

        return new self(
            operandPool: $intList($data['operandPool'] ?? []),
            operators: $operators,
            targetPool: $intList($data['targetPool'] ?? []),
            handSize: (int) ($data['handSize'] ?? 7),
            minimumCards: (int) ($data['minimumCards'] ?? 3),
            maximumCards: (int) ($data['maximumCards'] ?? 7),
            targetsPerMatch: (int) ($data['targetsPerMatch'] ?? 10),
            requireIntegerResult: (bool) ($data['requireIntegerResult'] ?? true),
            baseScore: (int) ($data['baseScore'] ?? 10),
            operandCopies: (int) ($data['operandCopies'] ?? 3),
            operatorCopies: (int) ($data['operatorCopies'] ?? 8),
        );
    }
}
