<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\DeckService;
use MathDeck\Http\Exception\Forbidden;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/decks/{deckVersionId}/publish
 *
 * The gate. Refuses with 422 and the full lint report when the deck would not be
 * playable, so the editor can show exactly which targets are the problem.
 */
final readonly class PublishDeckAction
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

        if ($this->decks->get($args['deckVersionId'])->authorId !== $identity->playerId) {
            throw Forbidden::because('Only the author can publish this deck.');
        }

        return Json::write($response, $this->decks->publish($args['deckVersionId'])->toArray());
    }
}
