<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Application\MatchService;
use MathDeck\Application\View\EventView;
use MathDeck\Application\View\MatchView;
use MathDeck\Engine\Command\Command;
use MathDeck\Engine\Command\Forfeit;
use MathDeck\Engine\Command\PlayCards;
use MathDeck\Engine\Event\Event;
use MathDeck\Http\Exception\BadRequest;
use MathDeck\Http\Json;
use MathDeck\Http\Middleware\IdempotencyKeyMiddleware;
use MathDeck\Http\PlayerIdentity;
use MathDeck\Http\Request\Body;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/matches/{matchId}/commands
 *
 * Returns 200 for every gameplay outcome, right or wrong. A rejected equation is a
 * successful request that produced a rejection event; only commands a correct
 * client could not have sent reach the error mapper.
 */
final readonly class PostCommandAction
{
    public function __construct(private MatchService $service)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);
        $matchId = $args['matchId'];

        $outcome = $this->service->execute($this->commandFrom($request, $matchId, $identity->playerId));

        if ($outcome->wasReplayed) {
            $response = $response->withHeader('Idempotency-Replayed', 'true');
        }

        return Json::write($response, [
            'replayed' => $outcome->wasReplayed,
            'events' => array_map(
                /** @param array{seq: int, event: Event} $numbered */
                static fn (array $numbered): array => [
                    'seq' => $numbered['seq'],
                    ...EventView::forPlayer($numbered['event'], $identity->playerId),
                ],
                $outcome->numberedEvents(),
            ),
            'state' => MatchView::forPlayer($outcome->state, $identity->playerId),
        ]);
    }

    private function commandFrom(ServerRequestInterface $request, string $matchId, string $playerId): Command
    {
        $body = Body::of($request);
        $clientCommandId = $request->getAttribute(IdempotencyKeyMiddleware::ATTRIBUTE);

        if (!is_string($clientCommandId)) {
            throw new \LogicException('The idempotency middleware did not run.');
        }

        // The player id comes from the authenticated identity. There is no code path
        // that would let the body name a different player.
        return match ($body->string('type')) {
            'play_cards' => new PlayCards(
                $matchId,
                $playerId,
                $body->stringList('cardIds', minimum: 1),
                $clientCommandId,
            ),
            'forfeit' => new Forfeit($matchId, $playerId, $clientCommandId),
            default => throw BadRequest::because('"type" must be "play_cards" or "forfeit".'),
        };
    }
}
