<?php

declare(strict_types=1);

namespace MathDeck\Engine;

use MathDeck\Engine\Event\Event;
use MathDeck\Engine\State\MatchState;

/**
 * Accumulates the events a command produces while keeping a folded state alongside
 * them.
 *
 * The engine reads that folded state when deciding what to emit next — how many
 * cards to draw depends on the hand *after* the played cards left it. Doing it this
 * way means the engine and the reducer cannot drift apart: there is only one
 * definition of what an event does.
 */
final class EventStream
{
    /** @var list<Event> */
    private array $events = [];

    public function __construct(private MatchState $state)
    {
    }

    public function emit(Event $event): void
    {
        $this->events[] = $event;
        $this->state = $this->state->apply($event);
    }

    public function state(): MatchState
    {
        return $this->state;
    }

    /** @return list<Event> */
    public function events(): array
    {
        return $this->events;
    }
}
