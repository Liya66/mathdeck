<?php

declare(strict_types=1);

namespace MathDeck\Tests\Integration;

use MathDeck\Application\EventSerializer;
use MathDeck\Application\MatchRepository;
use MathDeck\Application\MatchService;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\Engine;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Engine\State\MatchState;
use MathDeck\Engine\State\Phase;
use MathDeck\Infrastructure\Mysql\MysqlEventStore;
use MathDeck\Infrastructure\Mysql\MysqlMatchStore;
use MathDeck\Tests\Support\FrozenClock;

/**
 * The end-to-end claim: a match played through MySQL rebuilds from its seed and its
 * log with nothing lost.
 */
final class MatchPersistenceTest extends MysqlTestCase
{
    private const MATCH_ID = 'match-persisted';

    private MatchRepository $repository;
    private MatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $events = new MysqlEventStore($this->connection, new EventSerializer());
        $this->repository = new MatchRepository(new MysqlMatchStore($this->connection), $events);
        $this->service = new MatchService(new Engine(new FrozenClock()), $this->repository, $events);

        $this->repository->create(
            matchId: self::MATCH_ID,
            deckVersionId: 'deck-v1',
            seed: 20260115,
            rules: DeckRules::default(),
            playerIds: ['alice', 'bob'],
            createdAt: new \DateTimeImmutable('2026-01-15 09:00:00.000000', new \DateTimeZone('UTC')),
        );
    }

    public function testAMatchPlayedThroughTheDatabaseRebuildsIdentically(): void
    {
        $live = $this->repository->openingPosition(self::MATCH_ID);

        for ($turn = 0; $turn < 20 && $live->phase === Phase::AwaitingPlay; ++$turn) {
            $live = $this->play(sprintf('cmd-%d', $turn));
        }

        self::assertGreaterThan(0, $live->seq, 'The scripted match produced no events.');
        self::assertSame($live->fingerprint(), $this->repository->load(self::MATCH_ID)->fingerprint());
    }

    public function testTheDeckRulesAMatchWasCreatedWithSurviveAReload(): void
    {
        $rules = $this->repository->load(self::MATCH_ID)->rules;

        self::assertEquals(DeckRules::default(), $rules);
    }

    public function testARepeatedRequestIsNotAppliedTwice(): void
    {
        $state = $this->repository->load(self::MATCH_ID);
        $command = new PlayCards(self::MATCH_ID, $state->currentPlayer()->id, self::firstThree($state), 'cmd-once');

        $first = $this->service->execute($command);
        $second = $this->service->execute($command);

        self::assertFalse($first->wasReplayed);
        self::assertTrue($second->wasReplayed);
        self::assertSame($first->state->seq, $second->state->seq);
        self::assertEquals(
            array_map(static fn ($event): array => $event->payload(), $first->events),
            array_map(static fn ($event): array => $event->payload(), $second->events),
        );
    }

    private function play(string $commandId): MatchState
    {
        $state = $this->repository->load(self::MATCH_ID);

        return $this->service->execute(new PlayCards(
            self::MATCH_ID,
            $state->currentPlayer()->id,
            self::firstThree($state),
            $commandId,
        ))->state;
    }

    /** @return list<string> */
    private static function firstThree(MatchState $state): array
    {
        return array_map(
            static fn (Card $card): string => $card->id,
            array_slice($state->currentPlayer()->hand, 0, 3),
        );
    }
}
