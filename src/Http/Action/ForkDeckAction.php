<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\DeckService;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/decks/{deckVersionId}/fork
 *
 * How a published deck is changed. Anyone may fork a published deck — building on
 * a colleague's work is the point — and the fork belongs to whoever made it.
 */
final readonly class ForkDeckAction
{
    public function __construct(private DeckService $decks)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);
        $fork = $this->decks->fork($args['deckVersionId'], $identity->playerId);

        return Json::write(
            $response->withHeader('Location', sprintf('/v1/decks/%s', $fork->id())),
            $fork->toArray(),
            status: 201,
        );
    }
}
