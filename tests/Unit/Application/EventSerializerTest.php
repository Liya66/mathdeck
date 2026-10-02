<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Application;

use MathDeck\Application\EventSerializer;
use MathDeck\Application\Exception\UnknownEventType;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Card\Operator;
use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\InvalidPayload;
use MathDeck\Engine\Event\MatchEnded;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;
use MathDeck\Engine\Rule\RejectReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventSerializerTest extends TestCase
{
    private const AT = '2026-01-15 09:00:00.123456';

    #[DataProvider('everyEventType')]
    public function testEventsSurviveARoundTrip(Event $original): void
    {
        $serializer = new EventSerializer();

        $restored = $serializer->decode(
            $original->type(),
            $original->matchId(),
            $original->occurredAt(),
            $serializer->encode($original),
        );

        self::assertSame($original::class, $restored::class);
        self::assertSame($original->type(), $restored->type());
        self::assertSame($original->payload(), $restored->payload());
        self::assertEquals($original, $restored);
    }

    /** @return iterable<string, array{Event}> */
    public static function everyEventType(): iterable
    {
        $at = new \DateTimeImmutable(self::AT, new \DateTimeZone('UTC'));

        yield 'cards_played' => [new CardsPlayed('m1', $at, 'alice', ['a', 'b', 'c'], '3 + 4', 7, 1234)];
        yield 'cards_played without an expression' => [new CardsPlayed('m1', $at, 'alice', ['a'], null, 7, 0)];
        yield 'equation_solved' => [new EquationSolved('m1', $at, 'alice', ['a', 'b', 'c'], '3 + 4', 7, 10)];
        yield 'equation_rejected' => [
            new EquationRejected('m1', $at, 'alice', ['a'], '3 + 4', 9, RejectReason::OffByOne, '7'),
        ];
        yield 'equation_rejected with nulls' => [
            new EquationRejected('m1', $at, 'alice', [], null, 9, RejectReason::Malformed, null),
        ];
        yield 'cards_drawn' => [new CardsDrawn('m1', $at, 'alice', [
            Card::operand('c1', 7),
            Card::operator('c2', Operator::Divide),
        ])];
        yield 'target_revealed' => [new TargetRevealed('m1', $at, 18)];
        yield 'turn_ended' => [new TurnEnded('m1', $at, 'alice', 1)];
        yield 'match_ended' => [new MatchEnded('m1', $at, 'bob', 'forfeit')];
        yield 'match_ended drawn' => [new MatchEnded('m1', $at, null, 'targets_exhausted')];
    }

    /**
     * Forgetting to register a new event is otherwise discovered in production, on
     * a match that will not load.
     */
    public function testEveryEventClassInTheEngineIsRegistered(): void
    {
        $registered = EventSerializer::registeredTypes();

        foreach (self::eventClassesOnDisk() as $class) {
            self::assertContains(
                $class,
                $registered,
                sprintf('%s is not registered in EventSerializer.', $class),
            );
        }

        self::assertCount(count($registered), array_unique(array_keys($registered)));
    }

    public function testRegisteredTypeStringsMatchWhatTheEventsReport(): void
    {
        foreach (self::everyEventType() as [$event]) {
            self::assertArrayHasKey($event->type(), EventSerializer::registeredTypes());
            self::assertSame(
                $event::class,
                EventSerializer::registeredTypes()[$event->type()],
            );
        }
    }

    public function testAnUnknownTypeIsRefusedRatherThanSkipped(): void
    {
        $this->expectException(UnknownEventType::class);

        (new EventSerializer())->decode(
            'dragons_appeared',
            'm1',
            new \DateTimeImmutable(self::AT),
            '{}',
        );
    }

    public function testAPayloadMissingAFieldIsRefused(): void
    {
        $this->expectException(InvalidPayload::class);

        (new EventSerializer())->decode('turn_ended', 'm1', new \DateTimeImmutable(self::AT), '{"playerId":"alice"}');
    }

    public function testAPayloadWithAWrongTypeIsRefused(): void
    {
        $this->expectException(InvalidPayload::class);

        (new EventSerializer())->decode(
            'target_revealed',
            'm1',
            new \DateTimeImmutable(self::AT),
            '{"target":"eighteen"}',
        );
    }

    /** @return list<class-string<Event>> */
    private static function eventClassesOnDisk(): array
    {
        $directory = dirname(__DIR__, 3) . '/src/Engine/Event';
        $classes = [];

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            /** @var class-string $class */
            $class = 'MathDeck\\Engine\\Event\\' . basename($file, '.php');

            if (!class_exists($class)) {
                continue;
            }

            if (!in_array(Event::class, class_implements($class) ?: [], strict: true)) {
                continue;
            }

            /** @var class-string<Event> $class */
            $classes[] = $class;
        }

        self::assertNotSame([], $classes, 'No event classes found on disk; the glob is wrong.');

        return $classes;
    }
}
