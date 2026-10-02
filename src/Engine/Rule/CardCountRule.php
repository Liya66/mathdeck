<?php

declare(strict_types=1);

namespace MathDeck\Engine\Rule;

use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\State\MatchState;

final readonly class CardCountRule implements PlayRule
{
    public function check(MatchState $state, PlayCards $command): ?Violation
    {
        $count = count($command->cardIds);

        if ($count < $state->rules->minimumCards) {
            return Violation::gameplay(
                RejectReason::TooFewCards,
                sprintf('This deck needs at least %d cards, got %d.', $state->rules->minimumCards, $count),
            );
        }

        if ($count > $state->rules->maximumCards) {
            return Violation::gameplay(
                RejectReason::TooManyCards,
                sprintf('This deck allows at most %d cards, got %d.', $state->rules->maximumCards, $count),
            );
        }

        return null;
    }
}
