<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Application;

use MathDeck\Application\CommandOutcome;
use MathDeck\Application\Exception\ConcurrencyExhausted;
use MathDeck\Application\MatchRepository;
use MathDeck\Application\MatchService;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\Engine;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Exception\IllegalCommand;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Engine\State\Phase;
use MathDeck\Infrastructure\InMemory\InMemoryEventStore;
use MathDeck\Infrastructure\InMemory\InMemoryMatchStore;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class MatchServiceTest extends TestCase
{
    private const MATCH_ID = 'match-1';
    private const SEED = 20260115;

    private InMemoryEventStore $events;
    private MatchRepository $repository;
    private MatchService $service;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->events = new InMemoryEventStore();
        $this->repository = new MatchRepository(new InMemoryMatchStore(), $this->events);
        $this->service = new MatchService(new Engine($this->clock), $this->repository, $this->events);

        $this->repository->create(
            matchId: self::MATCH_ID,
            deckVersionId: 'deck-v1',
            seed: self::SEED,
            rules: DeckRules::default(),
            playerIds: ['alice', 'bob'],
            createdAt: $this->clock->now(),
        );
    }

    public function testExecutingACommandPersistsTheEventsItProduced(): void
    {
        $outcome = $this->play('cmd-1');

        self::assertFalse($outcome->wasReplayed);
        self::assertNotSame([], $outcome->events);
        self::assertSame(
            array_map(static fn (Event $event): string => $event->type(), $outcome->events),
            array_map(static fn (Event $event): string => $event->type(), $this->events->eventsOf(self::MATCH_ID)),
        );
    }

    public function testTheReturnedStateIsTheFoldOfTheStoredLog(): void
    {
        $this->play('cmd-1');
        $this->play('cmd-2');
        $outcome = $this->play('cmd-3');

        self::assertSame($this->repository->load(self::MATCH_ID)->fingerprint(), $outcome->state->fingerprint());
        self::assertSame(count($this->events->eventsOf(self::MATCH_ID)), $outcome->state->seq);
    }

    /**
     * A match is its seed plus its log. Nothing else is written, so this is the only
     * thing that makes persistence work at all.
     */
    public function testAMatchRebuildsFromItsSeedAndLogAlone(): void
    {
        $live = $this->repository->openingPosition(self::MATCH_ID);

        foreach (['cmd-1', 'cmd-2', 'cmd-3', 'cmd-4'] as $commandId) {
            $live = $this->play($commandId)->state;
        }

        self::assertSame($live->fingerprint(), $this->repository->load(self::MATCH_ID)->fingerprint());
    }

    /**
     * The flaky-wifi case: the same request arrives twice and must not play the
     * cards twice.
     */
    public function testRetryingACommandIdReplaysTheOriginalResult(): void
    {
        $first = $this->play('cmd-1');
        $storedAfterFirst = $this->events->eventsOf(self::MATCH_ID);

        $second = $this->service->execute($this->commandFor('cmd-1', $this->firstThreeCardIdsOfOpeningHand()));

        self::assertTrue($second->wasReplayed);
        self::assertEquals($first->events, $second->events);
        self::assertSame($storedAfterFirst, $this->events->eventsOf(self::MATCH_ID), 'Nothing new was written.');
        self::assertSame($first->state->fingerprint(), $second->state->fingerprint());
    }

    public function testALostRaceIsRetriedAgainstFreshState(): void
    {
        $this->injectCompetingWrite(times: 1);

        $outcome = $this->play('cmd-1');

        self::assertFalse($outcome->wasReplayed);
        $stored = $this->events->eventsOf(self::MATCH_ID);

        self::assertInstanceOf(TargetRevealed::class, $stored[0], 'The rival write landed first.');
        self::assertSame(count($stored), $outcome->state->seq, 'Our events were appended after it, not over it.');
    }

    public function testPersistentContentionEventuallyGivesUp(): void
    {
        $this->injectCompetingWrite(times: PHP_INT_MAX);

        $this->expectException(ConcurrencyExhausted::class);

        $this->play('cmd-1');
    }

    public function testARefusedCommandWritesNothing(): void
    {
        try {
            $this->service->execute(new PlayCards(
                self::MATCH_ID,
                'mallory',
                $this->firstThreeCardIdsOfOpeningHand(),
                'cmd-1',
            ));
            self::fail('Expected the command to be refused.');
        } catch (IllegalCommand) {
            self::assertSame([], $this->events->eventsOf(self::MATCH_ID));
            self::assertNull($this->events->findByClientCommandId(self::MATCH_ID, 'cmd-1'));
        }
    }

    public function testAMatchCanBePlayedThroughToItsEnd(): void
    {
        $state = $this->repository->openingPosition(self::MATCH_ID);

        for ($turn = 0; $turn < 25 && $state->phase === Phase::AwaitingPlay; ++$turn) {
            $state = $this->play(sprintf('cmd-%d', $turn))->state;
        }

        self::assertSame(Phase::Ended, $state->phase, 'The scripted match never finished.');
        self::assertSame($state->fingerprint(), $this->repository->load(self::MATCH_ID)->fingerprint());
    }

    private function play(string $commandId): CommandOutcome
    {
        $state = $this->repository->load(self::MATCH_ID);

        return $this->service->execute($this->commandFor($commandId, array_map(
            static fn (Card $card): string => $card->id,
            array_slice($state->currentPlayer()->hand, 0, 3),
        )));
    }

    /** @param list<string> $cardIds */
    private function commandFor(string $commandId, array $cardIds): PlayCards
    {
        return new PlayCards(
            self::MATCH_ID,
            $this->repository->load(self::MATCH_ID)->currentPlayer()->id,
            $cardIds,
            $commandId,
        );
    }

    /** @return list<string> */
    private function firstThreeCardIdsOfOpeningHand(): array
    {
        return array_map(
            static fn (Card $card): string => $card->id,
            array_slice($this->repository->load(self::MATCH_ID)->currentPlayer()->hand, 0, 3),
        );
    }

    /**
     * Simulates another writer claiming the next sequence number just before our
     * append lands.
     */
    private function injectCompetingWrite(int $times): void
    {
        $remaining = $times;

        $this->events->beforeAppend = function () use (&$remaining): void {
            if ($remaining < 1) {
                return;
            }

            --$remaining;

            $hook = $this->events->beforeAppend;
            $this->events->beforeAppend = null;

            $this->events->append(
                self::MATCH_ID,
                count($this->events->eventsOf(self::MATCH_ID)),
                [new TargetRevealed(self::MATCH_ID, $this->clock->now(), 99)],
                'rival-' . $remaining,
            );

            $this->events->beforeAppend = $hook;
        };
    }
}
