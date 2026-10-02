<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Engine\Event\Event;
use MathDeck\Engine\State\MatchState;

final readonly class CommandOutcome
{
    /**
     * @param list<Event> $events
     * @param int         $firstSeq sequence number of $events[0]; clients use it as
     *                              a cursor, so it has to be exact on a replay too
     */
    private function __construct(
        public array $events,
        public MatchState $state,
        public int $firstSeq,
        public bool $wasReplayed,
    ) {
    }

    /** @param list<Event> $events */
    public static function applied(array $events, MatchState $state, int $firstSeq): self
    {
        return new self($events, $state, $firstSeq, false);
    }

    /**
     * The command had already been accepted; these are the events it produced the
     * first time. The caller must not be able to tell the difference.
     *
     * @param list<Event> $events
     */
    public static function replayed(array $events, MatchState $state, int $firstSeq): self
    {
        return new self($events, $state, $firstSeq, true);
    }

    /** @return list<array{seq: int, event: Event}> */
    public function numberedEvents(): array
    {
        $numbered = [];

        foreach ($this->events as $offset => $event) {
            $numbered[] = ['seq' => $this->firstSeq + $offset, 'event' => $event];
        }

        return $numbered;
    }
}
