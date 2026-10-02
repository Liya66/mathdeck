<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\State\MatchState;

/**
 * Structural validation of a play. Rules answer "may this command be attempted at
 * all"; whether the arithmetic is right is the engine's business, not theirs.
 */
interface PlayRule
{
    public function check(MatchState $state, PlayCards $command): ?Violation;
}
