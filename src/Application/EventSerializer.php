<?php

declare(strict_types=1);

namespace MathDeck\Application;

use MathDeck\Application\Exception\UnknownEventType;
use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\MatchEnded;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;

/**
 * Wire format for the event log.
 *
 * The forward direction lives on each event (`type()` and `payload()`), the reverse
 * on the same class (`fromPayload()`), so a payload's shape is defined in exactly
 * one file. This class is only the registry that connects a stored type string back
 * to a class — and an unregistered type is an error, never a skipped row.
 */
final readonly class EventSerializer
{
    public function encode(Event $event): string
    {
        return json_encode($event->payload(), JSON_THROW_ON_ERROR);
    }

    public function decode(string $type, string $matchId, \DateTimeImmutable $occurredAt, string $json): Event
    {
        $payload = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new \RuntimeException(sprintf('Payload of %s is not an object.', $type));
        }

        /** @var array<string, mixed> $payload */
        return $this->fromPayload($type, $matchId, $occurredAt, $payload);
    }

    /** @param array<string, mixed> $payload */
    public function fromPayload(string $type, string $matchId, \DateTimeImmutable $occurredAt, array $payload): Event
    {
        return match ($type) {
            'cards_played' => CardsPlayed::fromPayload($matchId, $occurredAt, $payload),
            'equation_solved' => EquationSolved::fromPayload($matchId, $occurredAt, $payload),
            'equation_rejected' => EquationRejected::fromPayload($matchId, $occurredAt, $payload),
            'cards_drawn' => CardsDrawn::fromPayload($matchId, $occurredAt, $payload),
            'target_revealed' => TargetRevealed::fromPayload($matchId, $occurredAt, $payload),
            'turn_ended' => TurnEnded::fromPayload($matchId, $occurredAt, $payload),
            'match_ended' => MatchEnded::fromPayload($matchId, $occurredAt, $payload),
            default => throw UnknownEventType::named($type),
        };
    }

    /**
     * The registry, exposed so a test can assert that every event class in the
     * engine appears here. Forgetting one is only discovered on read, in production,
     * on a match that will not load.
     *
     * @return array<string, class-string<Event>>
     */
    public static function registeredTypes(): array
    {
        return [
            'cards_played' => CardsPlayed::class,
            'equation_solved' => EquationSolved::class,
            'equation_rejected' => EquationRejected::class,
            'cards_drawn' => CardsDrawn::class,
            'target_revealed' => TargetRevealed::class,
            'turn_ended' => TurnEnded::class,
            'match_ended' => MatchEnded::class,
        ];
    }
}
