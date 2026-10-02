<?php

declare(strict_types=1);

namespace MathDeck\Engine\Card;

enum CardKind: string
{
    case Operand = 'operand';
    case Operator = 'operator';
}
