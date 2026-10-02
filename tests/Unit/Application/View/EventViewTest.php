<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Application\View;

use MathDeck\Application\View\EventView;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Card\Operator;
use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\TurnEnded;
use PHPUnit\Framework\TestCase;

final class EventViewTest extends TestCase
{
    private const AT = '2026-01-15 09:00:00.123456';

    /**
     * Projecting state carefully and then streaming raw events to everyone leaks
     * precisely what the state projection was protecting.
     */
    public function testAnOpponentsDrawIsReducedToACount(): void
    {
        $event = self::aDraw();

        $asBob = EventView::forPlayer($event, 'bob');

        self::assertSame(
            ['playerId' => 'alice', 'cardCount' => 2],
            $asBob['payload'],
        );

        $json = json_encode($asBob, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('c101', $json);
        self::assertStringNotContainsString('c102', $json);
    }

    public function testTheDrawerSeesTheirOwnCards(): void
    {
        $asAlice = EventView::forPlayer(self::aDraw(), 'alice');
        $json = json_encode($asAlice, JSON_THROW_ON_ERROR);

        self::assertStringContainsString('c101', $json);
        self::assertStringContainsString('c102', $json);
    }

    public function testPublicEventsAreIdenticalForEveryone(): void
    {
        $played = new CardsPlayed('m1', self::at(), 'alice', ['c1', 'c2', 'c3'], '3 + 4', 7, 900);
        $ended = new TurnEnded('m1', self::at(), 'alice', 1);

        foreach ([$played, $ended] as $event) {
            self::assertSame(
                EventView::forPlayer($event, 'alice'),
                EventView::forPlayer($event, 'bob'),
                sprintf('%s should look the same to both players.', $event->type()),
            );
            self::assertSame($event->payload(), EventView::forPlayer($event, 'bob')['payload']);
        }
    }

    public function testTheEnvelopeCarriesTypeAndTimestamp(): void
    {
        $view = EventView::forPlayer(new TurnEnded('m1', self::at(), 'alice', 1), 'alice');

        self::assertSame('turn_ended', $view['type']);
        self::assertSame('2026-01-15T09:00:00.123456Z', $view['occurredAt']);
    }

    /**
     * A new event type must not be able to reach a client before somebody has
     * decided whether it is public.
     */
    public function testAnUndecidedEventTypeIsRefusedRatherThanPassedThrough(): void
    {
        $mystery = new class implements Event {
            public function matchId(): string
            {
                return 'm1';
            }

            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-01-15 09:00:00');
            }

            public function type(): string
            {
                return 'dragons_appeared';
            }

            public function payload(): array
            {
                return ['secret' => 'the whole draw pile'];
            }
        };

        $this->expectException(\LogicException::class);

        EventView::forPlayer($mystery, 'alice');
    }

    private static function aDraw(): CardsDrawn
    {
        return new CardsDrawn('m1', self::at(), 'alice', [
            Card::operand('c101', 9),
            Card::operator('c102', Operator::Multiply),
        ]);
    }

    private static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::AT, new \DateTimeZone('UTC'));
    }
}
