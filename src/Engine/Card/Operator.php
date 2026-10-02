<?php

declare(strict_types=1);

namespace MathDeck\Engine\Card;

use MathDeck\Engine\Math\Rational;

enum Operator: string
{
    case Add = '+';
    case Subtract = '-';
    case Multiply = '*';
    case Divide = '/';

    public function precedence(): int
    {
        return match ($this) {
            self::Add, self::Subtract => 1,
            self::Multiply, self::Divide => 2,
        };
    }

    public function apply(Rational $left, Rational $right): Rational
    {
        return match ($this) {
            self::Add => $left->add($right),
            self::Subtract => $left->subtract($right),
            self::Multiply => $left->multiply($right),
            self::Divide => $left->divide($right),
        };
    }
}
