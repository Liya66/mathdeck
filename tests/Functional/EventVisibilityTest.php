<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Tests\Support\FixedMatchIdentityFactory;
use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

/**
 * The leak test at the boundary that matters.
 *
 * The unit tests prove MatchView and EventView redact correctly. This proves the
 * wiring does too — that nothing between the projection and the socket puts the
 * hidden information back.
 */
final class EventVisibilityTest extends TestCase
{
    private const SOLVABLE_SEED = 6;

    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp(identity: new FixedMatchIdentityFactory(self::SOLVABLE_SEED));

        $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['alice', 'bob'],
        ], $this->app->authAs('alice'));

        $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'play_cards', 'cardIds' => $this->solvingCardIds()],
            $this->app->authAs('alice') + ['Idempotency-Key' => 'cmd-1'],
        );
    }

    public function testAnOpponentNeverSeesWhatWasDrawn(): void
    {
        $drawnIds = $this->cardIdsAliceDrew();
        self::assertNotEmpty($drawnIds, 'Alice should have drawn cards after solving.');

        $asBob = $this->app->bodyOf(
            $this->app->request('GET', '/v1/matches/match-1/events', null, $this->app->authAs('bob')),
        );

        foreach ($drawnIds as $cardId) {
            self::assertStringNotContainsString($cardId, $asBob, 'A drawn card leaked to the opponent.');
        }

        self::assertStringContainsString('cardCount', $asBob, 'The draw should still be visible as a count.');
    }

    public function testTheDrawerStillSeesTheirOwnDraw(): void
    {
        $asAlice = $this->app->bodyOf(
            $this->app->request('GET', '/v1/matches/match-1/events', null, $this->app->authAs('alice')),
        );

        foreach ($this->cardIdsAliceDrew() as $cardId) {
            self::assertStringContainsString($cardId, $asAlice);
        }
    }

    public function testAnOpponentsMatchViewCarriesNoHandOfYours(): void
    {
        $aliceHand = array_column(
            $this->app->json(
                $this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs('alice')),
            )['you']['hand'],
            'id',
        );

        $asBob = $this->app->bodyOf(
            $this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs('bob')),
        );

        foreach ($aliceHand as $cardId) {
            self::assertStringNotContainsString($cardId, $asBob, "Alice's hand leaked to bob.");
        }
    }

    public function testTheCursorSkipsEventsAlreadySeen(): void
    {
        $all = $this->app->json(
            $this->app->request('GET', '/v1/matches/match-1/events', null, $this->app->authAs('bob')),
        );
        $tail = $this->app->json(
            $this->app->request('GET', '/v1/matches/match-1/events?since=3', null, $this->app->authAs('bob')),
        );

        self::assertSame([1, 2, 3, 4, 5], array_column($all['events'], 'seq'));
        self::assertSame([4, 5], array_column($tail['events'], 'seq'));
        self::assertSame(3, $tail['since']);
    }

    public function testAStrangerCannotReadTheLog(): void
    {
        $response = $this->app->request('GET', '/v1/matches/match-1/events', null, $this->app->authAs('mallory'));

        self::assertSame(403, $response->getStatusCode());
    }

    /** @return list<string> */
    private function cardIdsAliceDrew(): array
    {
        $events = $this->app->json(
            $this->app->request('GET', '/v1/matches/match-1/events', null, $this->app->authAs('alice')),
        )['events'];

        foreach ($events as $event) {
            if ($event['type'] === 'cards_drawn') {
                return array_column($event['payload']['cards'], 'id');
            }
        }

        return [];
    }

    /** @return list<string> */
    private function solvingCardIds(): array
    {
        $view = $this->app->json(
            $this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs('alice')),
        );

        $operands = array_values(array_filter(
            $view['you']['hand'],
            static fn (array $card): bool => $card['kind'] === 'operand',
        ));
        $operators = array_values(array_filter(
            $view['you']['hand'],
            static fn (array $card): bool => $card['kind'] === 'operator',
        ));

        foreach ($operands as $left) {
            foreach ($operands as $right) {
                if ($left['id'] === $right['id']) {
                    continue;
                }

                foreach ($operators as $operator) {
                    if ($operator['operator'] === '+' && $left['value'] + $right['value'] === $view['target']) {
                        return [$left['id'], $operator['id'], $right['id']];
                    }
                }
            }
        }

        self::fail('No solving play in the opening hand; the pinned seed changed.');
    }

}
