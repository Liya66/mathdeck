<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Engine;

use MathDeck\Engine\Command\Forfeit;
use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\Engine;
use MathDeck\Engine\Event\CardsDrawn;
use MathDeck\Engine\Event\CardsPlayed;
use MathDeck\Engine\Event\EquationRejected;
use MathDeck\Engine\Event\EquationSolved;
use MathDeck\Engine\Event\Event;
use MathDeck\Engine\Event\MatchEnded;
use MathDeck\Engine\Event\TargetRevealed;
use MathDeck\Engine\Event\TurnEnded;
use MathDeck\Engine\Exception\IllegalCommand;
use MathDeck\Engine\Rule\RejectReason;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Engine\State\Phase;
use MathDeck\Tests\Support\Cards;
use MathDeck\Tests\Support\FrozenClock;
use MathDeck\Tests\Support\MatchBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    private FrozenClock $clock;
    private Engine $engine;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->engine = new Engine($this->clock);
    }

    /**
     * The table the whole game loop hangs off: a hand, the cards played from it,
     * the target on the board, and the verdict.
     *
     * @param list<int> $positions
     */
    #[DataProvider('attemptCases')]
    public function testAttemptOutcomes(
        string $hand,
        array $positions,
        int $target,
        ?RejectReason $expectedReason,
    ): void {
        $state = MatchBuilder::new()->withHand($hand)->withTarget($target)->build();
        $cardIds = Cards::idsAt(Cards::parse($hand), $positions);

        $events = $this->engine->handle($state, new PlayCards('match-1', 'alice', $cardIds, 'cmd-1'));

        if ($expectedReason === null) {
            self::assertInstanceOf(EquationSolved::class, self::find($events, EquationSolved::class));

            return;
        }

        $rejection = self::find($events, EquationRejected::class);
        self::assertInstanceOf(EquationRejected::class, $rejection);
        self::assertSame($expectedReason, $rejection->reason);
    }

    /** @return iterable<string, array{string, list<int>, int, RejectReason|null}> */
    public static function attemptCases(): iterable
    {
        yield 'simple sum hits the target' => ['3 + 4', [0, 1, 2], 7, null];
        yield 'precedence respected' => ['2 + 3 * 4', [0, 1, 2, 3, 4], 14, null];
        yield 'exact division' => ['1 / 3 * 3', [0, 1, 2, 3, 4], 1, null];

        yield 'precedence ignored is named as such' => [
            '2 + 3 * 4', [0, 1, 2, 3, 4], 20, RejectReason::OperatorPrecedenceIgnored,
        ];
        yield 'off by one is named as such' => ['3 + 4', [0, 1, 2], 8, RejectReason::OffByOne];
        yield 'plain wrong answer' => ['3 + 4', [0, 1, 2], 12, RejectReason::WrongTarget];
        yield 'division by zero' => ['6 / 0', [0, 1, 2], 5, RejectReason::DivisionByZero];
        yield 'non-integer result' => ['7 / 2', [0, 1, 2], 3, RejectReason::NonIntegerResult];
        yield 'operands not alternating' => ['3 4 +', [0, 1, 2], 7, RejectReason::Malformed];
        yield 'too few cards' => ['3 + 4', [0], 3, RejectReason::TooFewCards];
    }

    /**
     * A wrong reading that happens to agree with the right one must not be filed as
     * a precedence misconception, or the teacher's error clusters become noise.
     */
    public function testWrongAnswerIsNotBlamedOnPrecedenceWhenPrecedenceDoesNotApply(): void
    {
        $state = MatchBuilder::new()->withHand('2 * 3 + 4')->withTarget(99)->build();
        $cardIds = Cards::ids(Cards::parse('2 * 3 + 4'));

        $events = $this->engine->handle($state, new PlayCards('match-1', 'alice', $cardIds, 'cmd-1'));

        $rejection = self::find($events, EquationRejected::class);
        self::assertInstanceOf(EquationRejected::class, $rejection);
        self::assertSame(RejectReason::WrongTarget, $rejection->reason);
    }

    public function testSolvingScoresDrawsBackToHandSizeAndPassesTheTurn(): void
    {
        $state = MatchBuilder::new()->withHand('3 + 4')->withTarget(7)->withTargetQueue([5, 9])->build();
        $cardIds = Cards::ids(Cards::parse('3 + 4'));

        $events = $this->engine->handle($state, new PlayCards('match-1', 'alice', $cardIds, 'cmd-1'));
        $next = $state->applyAll($events);

        $solved = self::find($events, EquationSolved::class);
        self::assertInstanceOf(EquationSolved::class, $solved);
        self::assertSame(10, $solved->score, 'One low-precedence operator scores baseScore x 1.');

        $drawn = self::find($events, CardsDrawn::class);
        self::assertInstanceOf(CardsDrawn::class, $drawn);

        $alice = $next->playerById('alice');
        self::assertNotNull($alice);
        self::assertSame(10, $alice->score);
        self::assertCount($state->rules->handSize, $alice->hand, 'Hand refilled to the deck limit.');

        self::assertInstanceOf(TargetRevealed::class, self::find($events, TargetRevealed::class));
        self::assertSame(5, $next->board->target);
        self::assertSame(1, $next->currentPlayerIndex);
    }

    public function testMultiplicationScoresHigherThanAddition(): void
    {
        $state = MatchBuilder::new()->withHand('2 + 3 * 4')->withTarget(14)->build();
        $cardIds = Cards::ids(Cards::parse('2 + 3 * 4'));

        $events = $this->engine->handle($state, new PlayCards('match-1', 'alice', $cardIds, 'cmd-1'));

        $solved = self::find($events, EquationSolved::class);
        self::assertInstanceOf(EquationSolved::class, $solved);
        self::assertSame(30, $solved->score, 'Two operators plus a high-precedence bonus.');
    }

    /**
     * A wrong answer costs the turn, not the cards. The same hand can be tried
     * again, which is the point pedagogically.
     */
    public function testRejectedAttemptKeepsTheCardsButLosesTheTurn(): void
    {
        $state = MatchBuilder::new()->withHand('3 + 4')->withTarget(12)->build();
        $cardIds = Cards::ids(Cards::parse('3 + 4'));

        $events = $this->engine->handle($state, new PlayCards('match-1', 'alice', $cardIds, 'cmd-1'));
        $next = $state->applyAll($events);

        $alice = $next->playerById('alice');
        self::assertNotNull($alice);
        self::assertCount(3, $alice->hand);
        self::assertSame(0, $alice->score);
        self::assertSame(1, $next->currentPlayerIndex);
        self::assertInstanceOf(TurnEnded::class, self::find($events, TurnEnded::class));
    }

    /**
     * Every attempt is recorded, right or wrong. Without this the dashboard can
     * count successes but not effort.
     */
    public function testEveryAttemptIsRecordedWithServerMeasuredLatency(): void
    {
        $state = MatchBuilder::new()
            ->withHand('3 + 4')
            ->withTarget(12)
            ->turnStartedAt('2026-01-15 09:00:00.000000')
            ->build();

        $this->clock->advanceMs(4200);

        $events = $this->engine->handle(
            $state,
            new PlayCards('match-1', 'alice', Cards::ids(Cards::parse('3 + 4')), 'cmd-1'),
        );

        $played = self::find($events, CardsPlayed::class);
        self::assertInstanceOf(CardsPlayed::class, $played);
        self::assertSame(4200, $played->latencyMs);
        self::assertSame('3 + 4', $played->expression);
        self::assertSame(12, $played->target);
    }

    #[DataProvider('illegalCommandCases')]
    public function testCommandsACorrectClientCannotSendAreRefusedOutright(
        string $case,
        RejectReason $expectedReason,
    ): void {
        [$state, $cardIds, $playerId] = self::illegalScenario($case);

        try {
            $this->engine->handle($state, new PlayCards('match-1', $playerId, $cardIds, 'cmd-1'));
            self::fail('Expected the command to be refused.');
        } catch (IllegalCommand $refused) {
            self::assertSame($expectedReason, $refused->reason);
        }
    }

    /** @return iterable<string, array{string, RejectReason}> */
    public static function illegalCommandCases(): iterable
    {
        yield 'playing out of turn' => ['out_of_turn', RejectReason::NotYourTurn];
        yield 'playing a card not held' => ['card_not_held', RejectReason::CardNotInHand];
        yield 'playing in a finished match' => ['ended', RejectReason::MatchNotInPlay];
        yield 'a stranger playing' => ['stranger', RejectReason::NotYourTurn];
    }

    /** @return array{0: \MathDeck\Engine\State\MatchState, 1: list<string>, 2: string} */
    private static function illegalScenario(string $case): array
    {
        $validIds = Cards::ids(Cards::parse('3 + 4'));

        return match ($case) {
            'out_of_turn' => [
                MatchBuilder::new()->withOpponentToPlay()->build(), $validIds, 'alice',
            ],
            'card_not_held' => [
                MatchBuilder::new()->build(), ['h0', 'h1', 'not-mine'], 'alice',
            ],
            'ended' => [
                MatchBuilder::new()->endedMatch()->build(), $validIds, 'alice',
            ],
            'stranger' => [
                MatchBuilder::new()->build(), $validIds, 'mallory',
            ],
            default => throw new \LogicException('Unknown scenario ' . $case),
        };
    }

    public function testTooManyCardsIsADeckRuleNotAProtocolFault(): void
    {
        $rules = new DeckRules(
            operandPool: range(0, 12),
            operators: \MathDeck\Engine\Card\Operator::cases(),
            targetPool: range(1, 24),
            maximumCards: 3,
        );

        $state = MatchBuilder::new()->withHand('2 + 3 * 4')->withTarget(14)->withRules($rules)->build();

        $events = $this->engine->handle(
            $state,
            new PlayCards('match-1', 'alice', Cards::ids(Cards::parse('2 + 3 * 4')), 'cmd-1'),
        );

        $rejection = self::find($events, EquationRejected::class);
        self::assertInstanceOf(EquationRejected::class, $rejection);
        self::assertSame(RejectReason::TooManyCards, $rejection->reason);
    }

    public function testMatchEndsWhenTheLastTargetIsCleared(): void
    {
        $state = MatchBuilder::new()->withHand('3 + 4')->withTarget(7)->withTargetQueue([])->build();

        $events = $this->engine->handle(
            $state,
            new PlayCards('match-1', 'alice', Cards::ids(Cards::parse('3 + 4')), 'cmd-1'),
        );
        $next = $state->applyAll($events);

        $ended = self::find($events, MatchEnded::class);
        self::assertInstanceOf(MatchEnded::class, $ended);
        self::assertSame('targets_exhausted', $ended->reason);
        self::assertSame('alice', $ended->winnerId);
        self::assertSame(Phase::Ended, $next->phase);
        self::assertNull(self::find($events, TurnEnded::class));
    }

    public function testMatchEndsWhenTheDrawPileRunsOut(): void
    {
        $state = MatchBuilder::new()->withHand('3 + 4')->withTarget(7)->withDrawPileSize(4)->build();

        $events = $this->engine->handle(
            $state,
            new PlayCards('match-1', 'alice', Cards::ids(Cards::parse('3 + 4')), 'cmd-1'),
        );

        $ended = self::find($events, MatchEnded::class);
        self::assertInstanceOf(MatchEnded::class, $ended);
        self::assertSame('deck_exhausted', $ended->reason);
    }

    public function testForfeitHandsTheMatchToTheOpponent(): void
    {
        $state = MatchBuilder::new()->build();

        $events = $this->engine->handle($state, new Forfeit('match-1', 'alice', 'cmd-1'));
        $next = $state->applyAll($events);

        self::assertCount(1, $events);
        $ended = self::find($events, MatchEnded::class);
        self::assertInstanceOf(MatchEnded::class, $ended);
        self::assertSame('bob', $ended->winnerId);
        self::assertSame('forfeit', $ended->reason);
        self::assertSame(Phase::Ended, $next->phase);
    }

    public function testForfeitingAFinishedMatchIsRefused(): void
    {
        $this->expectException(IllegalCommand::class);

        $this->engine->handle(MatchBuilder::new()->endedMatch()->build(), new Forfeit('match-1', 'alice', 'cmd-1'));
    }

    /**
     * @param list<Event>     $events
     * @param class-string    $type
     */
    private static function find(array $events, string $type): ?Event
    {
        foreach ($events as $event) {
            if ($event instanceof $type) {
                return $event;
            }
        }

        return null;
    }
}
