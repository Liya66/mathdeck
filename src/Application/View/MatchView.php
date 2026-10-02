<?php

declare(strict_types=1);

namespace MathDeck\Application\View;

use MathDeck\Application\Exception\PlayerNotInMatch;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\State\MatchState;
use MathDeck\Engine\State\PlayerState;

/**
 * What one player is allowed to see.
 *
 * This is a security boundary, not a serialisation convenience. MatchState holds
 * every hand, the entire draw pile in order, and the seed that generated both —
 * handing any of that to a client ends the game as a game. Three things are
 * deliberately absent and must stay absent:
 *
 *   - other players' hands (counts only)
 *   - the draw pile's contents (a count only)
 *   - the seed, which *is* the draw pile, in four bytes
 *
 * The test for this greps the encoded JSON for values that should not be reachable,
 * rather than asserting on key names. Key-name assertions keep passing while a
 * nested field quietly leaks.
 */
final readonly class MatchView
{
    /** @return array<string, mixed> */
    public static function forPlayer(MatchState $state, string $playerId): array
    {
        $you = $state->playerById($playerId) ?? throw PlayerNotInMatch::of($playerId, $state->matchId);

        return [
            'matchId' => $state->matchId,
            'deckVersionId' => $state->deckVersionId,
            'seq' => $state->seq,
            'phase' => $state->phase->value,
            'target' => $state->board->target,
            'targetsRemaining' => count($state->board->targetQueue),
            'drawPileCount' => count($state->board->drawPile),
            'yourTurn' => $state->isCurrentPlayer($playerId),
            'winnerId' => $state->winnerId,
            'you' => [
                'id' => $you->id,
                'score' => $you->score,
                'hand' => array_map(static fn (Card $card): array => $card->toArray(), $you->hand),
            ],
            'opponents' => array_values(array_map(
                static fn (PlayerState $player): array => [
                    'id' => $player->id,
                    'score' => $player->score,
                    'handCount' => count($player->hand),
                ],
                array_filter(
                    $state->players,
                    static fn (PlayerState $player): bool => $player->id !== $playerId,
                ),
            )),
            // Echoed so the client can validate a play before sending it. Advisory
            // only: the server re-checks everything here.
            'rules' => [
                'handSize' => $state->rules->handSize,
                'minimumCards' => $state->rules->minimumCards,
                'maximumCards' => $state->rules->maximumCards,
                'requireIntegerResult' => $state->rules->requireIntegerResult,
            ],
        ];
    }
}
