<?php

declare(strict_types=1);

namespace MathDeck\Application\View;

use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\MatchEnded;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;

/**
 * Per-player view of an event.
 *
 * Projecting match state carefully and then streaming raw events to both clients
 * leaks exactly what the state projection was protecting: CardsDrawn carries real
 * cards, so an opponent's draw — and with it the order of the pile — goes out on
 * the wire.
 *
 * Visibility is decided by an exhaustive match with no default case that guesses.
 * A new event type will not compile its way past this: someone has to say whether
 * it is public.
 */
final readonly class EventView
{
    /** @return array<string, mixed> */
    public static function forPlayer(Event $event, string $playerId): array
    {
        return [
            'type' => $event->type(),
            'occurredAt' => $event->occurredAt()->format('Y-m-d\TH:i:s.up'),
            'payload' => self::payloadFor($event, $playerId),
        ];
    }

    /** @return array<string, mixed> */
    private static function payloadFor(Event $event, string $playerId): array
    {
        return match (true) {
            // Public: these describe cards laid on the table in front of everyone.
            $event instanceof CardsPlayed,
            $event instanceof EquationSolved,
            $event instanceof EquationRejected,
            $event instanceof TargetRevealed,
            $event instanceof TurnEnded,
            $event instanceof MatchEnded => $event->payload(),

            // Private: only the drawer sees what was drawn. Everyone else is told
            // how many, which is all they could infer from watching anyway.
            $event instanceof CardsDrawn => $event->playerId === $playerId
                ? $event->payload()
                : ['playerId' => $event->playerId, 'cardCount' => count($event->cards)],

            default => throw new \LogicException(sprintf(
                'No visibility decision for %s. Decide whether it is public before shipping it.',
                $event::class,
            )),
        };
    }
}
