<?php

declare(strict_types=1);

namespace MathDeck\Engine\State;

enum Phase: string
{
    case AwaitingPlay = 'awaiting_play';
    case Ended = 'ended';
}
