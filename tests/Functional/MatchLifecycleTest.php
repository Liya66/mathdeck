<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class MatchLifecycleTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
    }

    public function testCreatingAMatchReturnsTheCreatorsOwnView(): void
    {
        $response = $this->createMatch();

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('/v1/matches/match-1', $response->getHeaderLine('Location'));

        $view = $this->app->json($response);

        self::assertSame('match-1', $view['matchId']);
        self::assertSame('starter@1', $view['deckVersionId']);
        self::assertSame(0, $view['seq']);
        self::assertSame('awaiting_play', $view['phase']);
        self::assertTrue($view['yourTurn']);
        self::assertIsArray($view['you']);
        self::assertSame('alice', $view['you']['id']);
        self::assertCount(7, $view['you']['hand']);
        self::assertSame([['id' => 'bob', 'score' => 0, 'handCount' => 7]], $view['opponents']);
    }

    public function testTheSeedIsNeverAcceptedFromAClient(): void
    {
        // Sending a seed does not make it the seed: the field is not read anywhere.
        $withSeed = (new TestApp())->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['alice', 'bob'],
            'seed' => 1,
        ], $this->app->authAs('alice'), validateRequest: false);

        $chosenByServer = $this->app->json($this->createMatch());
        $ignoredRequest = $this->app->json($withSeed);

        self::assertSame(
            $chosenByServer['you'],
            $ignoredRequest['you'],
            'The deal must be identical, i.e. the supplied seed had no effect.',
        );
    }

    public function testAnUnauthenticatedRequestIsRefused(): void
    {
        $response = $this->app->request('GET', '/v1/matches/match-1', null, [], validateRequest: false);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Unauthenticated', $this->app->json($response)['title']);
    }

    public function testAPlayerCannotReadAMatchTheyAreNotIn(): void
    {
        $this->createMatch();

        $response = $this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs('mallory'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testReadingAMatchThatDoesNotExistIs404(): void
    {
        $response = $this->app->request('GET', '/v1/matches/nope', null, $this->app->authAs('alice'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Match not found', $this->app->json($response)['title']);
    }

    public function testAnUnpublishedDeckIs404(): void
    {
        $response = $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'deck-does-not-exist',
            'playerIds' => ['alice', 'bob'],
        ], $this->app->authAs('alice'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Deck version not found', $this->app->json($response)['title']);
    }

    public function testAMatchNeedsAtLeastTwoDistinctPlayers(): void
    {
        $tooFew = $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['alice'],
        ], $this->app->authAs('alice'), validateRequest: false);

        $repeated = $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['alice', 'alice'],
        ], $this->app->authAs('alice'));

        self::assertSame(400, $tooFew->getStatusCode());
        self::assertSame(400, $repeated->getStatusCode());
    }

    public function testYouCannotCreateAMatchYouAreNotPlayingIn(): void
    {
        $response = $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['bob', 'carol'],
        ], $this->app->authAs('alice'));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testBothPlayersSeeTheSameBoardAndDifferentHands(): void
    {
        $this->createMatch();

        $asAlice = $this->app->json($this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs('alice')));
        $asBob = $this->app->json($this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs('bob')));

        self::assertSame($asAlice['target'], $asBob['target']);
        self::assertSame($asAlice['drawPileCount'], $asBob['drawPileCount']);
        self::assertTrue($asAlice['yourTurn']);
        self::assertFalse($asBob['yourTurn']);
        self::assertNotSame($asAlice['you'], $asBob['you']);
    }

    private function createMatch(): \Psr\Http\Message\ResponseInterface
    {
        return $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['alice', 'bob'],
        ], $this->app->authAs('alice'));
    }

}
