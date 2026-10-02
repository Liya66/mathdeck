<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\State\MatchState;

final readonly class TurnOrderRule implements PlayRule
{
    public function check(MatchState $state, PlayCards $command): ?Violation
    {
        if ($state->playerById($command->playerId()) === null) {
            return Violation::protocol(
                RejectReason::NotYourTurn,
                sprintf('%s is not in match %s.', $command->playerId(), $state->matchId),
            );
        }

        return $state->isCurrentPlayer($command->playerId())
            ? null
            : Violation::protocol(
                RejectReason::NotYourTurn,
                sprintf('It is %s\'s turn, not %s\'s.', $state->currentPlayer()->id, $command->playerId()),
            );
    }
}
