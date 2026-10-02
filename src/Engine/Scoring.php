<?php

declare(strict_types=1);

namespace MathDeck\Engine;

use MathDeck\Engine\Math\Expression;
use MathDeck\Engine\State\DeckRules;

/**
 * Reward length and reward reaching for multiplication or division, so the cheapest
 * correct answer is not always the best one.
 */
final readonly class Scoring
{
    public static function forExpression(Expression $expression, DeckRules $rules): int
    {
        $difficulty = $expression->operatorCount()
            + ($expression->usesHighPrecedenceOperator() ? 1 : 0);

        return $rules->baseScore * $difficulty;
    }
}
