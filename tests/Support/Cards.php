<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Card\Operator;

/**
 * Turns "3 + 4 * 2" into cards, so test cases read like the thing they describe.
 */
final class Cards
{
    /** @return list<Card> */
    public static function parse(string $specification, string $prefix = 'h'): array
    {
        $tokens = preg_split('/\s+/', trim($specification), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $cards = [];

        foreach ($tokens as $index => $token) {
            $id = sprintf('%s%d', $prefix, $index);

            $cards[] = is_numeric($token)
                ? Card::operand($id, (int) $token)
                : Card::operator($id, Operator::from($token));
        }

        return $cards;
    }

    /**
     * @param list<Card> $cards
     *
     * @return list<string>
     */
    public static function ids(array $cards): array
    {
        return array_map(static fn (Card $card): string => $card->id, $cards);
    }

    /**
     * @param list<Card> $cards
     * @param list<int>  $positions
     *
     * @return list<string>
     */
    public static function idsAt(array $cards, array $positions): array
    {
        return array_map(static fn (int $position): string => $cards[$position]->id, $positions);
    }
}
