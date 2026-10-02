<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\State\MatchState;
use MathDeck\Engine\State\Phase;

final readonly class MatchInPlayRule implements PlayRule
{
    public function check(MatchState $state, PlayCards $command): ?Violation
    {
        return $state->phase === Phase::AwaitingPlay
            ? null
            : Violation::protocol(
                RejectReason::MatchNotInPlay,
                sprintf('Match %s is %s.', $state->matchId, $state->phase->value),
            );
    }
}
