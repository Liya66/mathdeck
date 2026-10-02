<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

enum Severity
{
    /** A correct client cannot produce this. Refuse the command outright. */
    case Protocol;

    /** A plausible thing for a learner to try. Record it and cost them the turn. */
    case Gameplay;
}
