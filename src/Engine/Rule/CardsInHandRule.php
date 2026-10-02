<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\State\MatchState;

/**
 * The anti-tamper rule. A client that names a card it does not hold is either
 * broken or lying, and either way the server does not look it up.
 */
final readonly class CardsInHandRule implements PlayRule
{
    public function check(MatchState $state, PlayCards $command): ?Violation
    {
        $player = $state->playerById($command->playerId());

        if ($player === null || !$player->holdsAll($command->cardIds)) {
            return Violation::protocol(
                RejectReason::CardNotInHand,
                sprintf('%s does not hold every card played.', $command->playerId()),
            );
        }

        return null;
    }
}
