<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Engine\Event\Event;

final readonly class StoredEvent
{
    public function __construct(
        public int $seq,
        public Event $event,
    ) {
    }
}
