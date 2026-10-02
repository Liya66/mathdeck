<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Application\View;

use MathDeck\Application\Exception\PlayerNotInMatch;
use MathDeck\Application\View\MatchView;
use MathDeck\Engine\Card\Card;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Engine\State\MatchState;
use PHPUnit\Framework\TestCase;

final class MatchViewTest extends TestCase
{
    private const SEED = 20260115;

    /**
     * The test that matters. It greps the encoded response for values the viewer
     * must not be able to reach, rather than asserting that a key is absent —
     * key-name assertions keep passing while a nested field leaks.
     */
    public function testAPlayerCannotSeeAnythingThatWouldEndTheGame(): void
    {
        $state = self::match();
        $json = json_encode(MatchView::forPlayer($state, 'alice'), JSON_THROW_ON_ERROR);

        foreach (self::cardIdsOf($state, 'bob') as $cardId) {
            self::assertStringNotContainsString($cardId, $json, 'An opponent hand card leaked.');
        }

        foreach (self::drawPileIds($state) as $cardId) {
            self::assertStringNotContainsString($cardId, $json, 'A draw pile card leaked.');
        }

        // The seed is the draw pile, in four bytes.
        self::assertStringNotContainsString((string) self::SEED, $json, 'The match seed leaked.');
    }

    public function testTheViewerSeesTheirOwnHandInFull(): void
    {
        $state = self::match();
        $view = MatchView::forPlayer($state, 'alice');
        $json = json_encode($view, JSON_THROW_ON_ERROR);

        foreach (self::cardIdsOf($state, 'alice') as $cardId) {
            self::assertStringContainsString($cardId, $json);
        }

        self::assertIsArray($view['you']);
        self::assertCount($state->rules->handSize, $view['you']['hand']);
    }

    public function testOpponentsAreReducedToCounts(): void
    {
        $view = MatchView::forPlayer(self::match(), 'alice');

        self::assertSame(
            [['id' => 'bob', 'score' => 0, 'handCount' => 7]],
            $view['opponents'],
        );
    }

    public function testHiddenInformationIsReportedAsCounts(): void
    {
        $state = self::match();
        $view = MatchView::forPlayer($state, 'alice');

        self::assertSame(count($state->board->drawPile), $view['drawPileCount']);
        self::assertSame(count($state->board->targetQueue), $view['targetsRemaining']);
        self::assertSame($state->board->target, $view['target']);
    }

    public function testTurnOwnershipIsStatedFromTheViewersPerspective(): void
    {
        $state = self::match();

        self::assertTrue(MatchView::forPlayer($state, 'alice')['yourTurn']);
        self::assertFalse(MatchView::forPlayer($state, 'bob')['yourTurn']);
    }

    public function testAStrangerGetsNoViewAtAll(): void
    {
        $this->expectException(PlayerNotInMatch::class);

        MatchView::forPlayer(self::match(), 'mallory');
    }

    /** @return list<string> */
    private static function cardIdsOf(MatchState $state, string $playerId): array
    {
        $player = $state->playerById($playerId);
        self::assertNotNull($player);

        return array_map(static fn (Card $card): string => $card->id, $player->hand);
    }

    /** @return list<string> */
    private static function drawPileIds(MatchState $state): array
    {
        return array_map(static fn (Card $card): string => $card->id, $state->board->drawPile);
    }

    private static function match(): MatchState
    {
        return MatchState::start(
            matchId: 'match-1',
            deckVersionId: 'deck-v1',
            seed: self::SEED,
            rules: DeckRules::default(),
            playerIds: ['alice', 'bob'],
            startedAt: new \DateTimeImmutable('2026-01-15 09:00:00', new \DateTimeZone('UTC')),
        );
    }
}
