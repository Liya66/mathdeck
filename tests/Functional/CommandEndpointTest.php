<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Tests\Support\FixedMatchIdentityFactory;
use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class CommandEndpointTest extends TestCase
{
    /** A deal where alice can reach the opening target with two cards and an operator. */
    private const SOLVABLE_SEED = 6;

    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp(identity: new FixedMatchIdentityFactory(self::SOLVABLE_SEED));
        $this->createMatch();
    }

    public function testSolvingTheTargetScoresAndPassesTheTurn(): void
    {
        $response = $this->play($this->solvingCardIds(), 'cmd-1');
        $body = $this->app->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($body['replayed']);
        self::assertSame(
            ['cards_played', 'equation_solved', 'cards_drawn', 'target_revealed', 'turn_ended'],
            array_column($body['events'], 'type'),
        );
        self::assertSame([1, 2, 3, 4, 5], array_column($body['events'], 'seq'));
        self::assertGreaterThan(0, $body['state']['you']['score']);
        self::assertFalse($body['state']['yourTurn']);
    }

    /**
     * The status code decision worth arguing about. A learner who misses the target
     * made a perfectly good request.
     */
    public function testAWrongAnswerIsASuccessfulRequest(): void
    {
        $response = $this->play($this->malformedCardIds(), 'cmd-1');
        $body = $this->app->json($response);

        self::assertSame(200, $response->getStatusCode());

        $rejection = $this->eventOfType($body['events'], 'equation_rejected');
        self::assertNotNull($rejection);
        self::assertSame('MALFORMED', $rejection['payload']['reason']);
        self::assertSame(0, $body['state']['you']['score']);
    }

    public function testPlayingOutOfTurnIsRefusedWithAReasonCode(): void
    {
        $response = $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'play_cards', 'cardIds' => ['whatever', 'x', 'y']],
            $this->app->authAs('bob') + ['Idempotency-Key' => 'cmd-1'],
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('NOT_YOUR_TURN', $this->app->json($response)['reason']);
    }

    public function testACardTheCallerDoesNotHoldIsRefused(): void
    {
        $response = $this->play(['not-mine', 'nor-this', 'nor-that'], 'cmd-1');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CARD_NOT_IN_HAND', $this->app->json($response)['reason']);
    }

    public function testAnIdempotencyKeyIsRequired(): void
    {
        $response = $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'play_cards', 'cardIds' => $this->solvingCardIds()],
            $this->app->authAs('alice'),
            validateRequest: false,
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Idempotency-Key', $this->app->json($response)['detail']);
    }

    public function testRetryingWithTheSameKeyReplaysInsteadOfPlayingTwice(): void
    {
        $cardIds = $this->solvingCardIds();

        $first = $this->app->json($this->play($cardIds, 'cmd-1'));
        $response = $this->play($cardIds, 'cmd-1');
        $second = $this->app->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->getHeaderLine('Idempotency-Replayed'));
        self::assertTrue($second['replayed']);
        self::assertSame($first['events'], $second['events']);
        self::assertSame($first['state']['seq'], $second['state']['seq'], 'The match did not advance twice.');
    }

    public function testForfeitEndsTheMatch(): void
    {
        $response = $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'forfeit'],
            $this->app->authAs('alice') + ['Idempotency-Key' => 'cmd-1'],
        );
        $body = $this->app->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['match_ended'], array_column($body['events'], 'type'));
        self::assertSame('ended', $body['state']['phase']);
        self::assertSame('bob', $body['state']['winnerId']);
    }

    public function testAnUnknownCommandTypeIsRejected(): void
    {
        $response = $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'summon_dragon'],
            $this->app->authAs('alice') + ['Idempotency-Key' => 'cmd-1'],
            validateRequest: false,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testPlayingInAFinishedMatchIsRefused(): void
    {
        $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'forfeit'],
            $this->app->authAs('alice') + ['Idempotency-Key' => 'cmd-1'],
        );

        $response = $this->play($this->solvingCardIds(), 'cmd-2');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('MATCH_NOT_IN_PLAY', $this->app->json($response)['reason']);
    }

    /** @param list<string> $cardIds */
    private function play(array $cardIds, string $commandId): ResponseInterface
    {
        return $this->app->request(
            'POST',
            '/v1/matches/match-1/commands',
            ['type' => 'play_cards', 'cardIds' => $cardIds],
            $this->app->authAs('alice') + ['Idempotency-Key' => $commandId],
        );
    }

    /**
     * Finds a two-operand expression in the caller's hand that hits the target, the
     * same way a client would.
     *
     * @return list<string>
     */
    private function solvingCardIds(): array
    {
        $view = $this->currentView();
        $target = $view['target'];
        $hand = $view['you']['hand'];

        $operands = array_values(array_filter($hand, static fn (array $c): bool => $c['kind'] === 'operand'));
        $operators = array_values(array_filter($hand, static fn (array $c): bool => $c['kind'] === 'operator'));

        foreach ($operands as $left) {
            foreach ($operands as $right) {
                if ($left['id'] === $right['id']) {
                    continue;
                }

                foreach ($operators as $operator) {
                    if (self::evaluate($left['value'], $operator['operator'], $right['value']) === $target) {
                        return [$left['id'], $operator['id'], $right['id']];
                    }
                }
            }
        }

        self::fail('No solving play in the opening hand; the pinned seed changed.');
    }

    /** @return list<string> */
    private function malformedCardIds(): array
    {
        $hand = $this->currentView()['you']['hand'];
        $operands = array_values(array_filter($hand, static fn (array $c): bool => $c['kind'] === 'operand'));

        // Three operands in a row. A client that is not checking would send this,
        // which is the whole reason the server checks.
        return [$operands[0]['id'], $operands[1]['id'], $operands[2]['id']];
    }

    private static function evaluate(int $left, string $operator, int $right): ?int
    {
        return match ($operator) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right !== 0 && $left % $right === 0 ? intdiv($left, $right) : null,
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return array<string, mixed>|null
     */
    private function eventOfType(array $events, string $type): ?array
    {
        foreach ($events as $event) {
            if ($event['type'] === $type) {
                return $event;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function currentView(): array
    {
        return $this->app->json(
            $this->app->request('GET', '/v1/matches/match-1', null, $this->app->authAs('alice')),
        );
    }

    private function createMatch(): void
    {
        $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'starter@1',
            'playerIds' => ['alice', 'bob'],
        ], $this->app->authAs('alice'));
    }

}
