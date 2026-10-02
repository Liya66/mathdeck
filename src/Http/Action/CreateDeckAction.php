<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Authoring\DeckService;
use MathDeck\Http\Exception\BadRequest;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/decks
 *
 * Creates a draft. Drafts are not validated on the way in: a teacher halfway
 * through authoring should be able to save, and the publish gate is where
 * correctness is enforced.
 */
final readonly class CreateDeckAction
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
        $body = $request->getParsedBody();

        $definition = is_array($body) && is_array($body['definition'] ?? null)
            ? $body['definition']
            : DeckDocument::starter()->toArray();

        if (!is_array($body)) {
            throw BadRequest::because('The request body must be a JSON object.');
        }

        /** @var array<string, mixed> $definition */
        $draft = $this->decks->createDraft($identity->playerId, $definition);

        return Json::write(
            $response->withHeader('Location', sprintf('/v1/decks/%s', $draft->id())),
            $draft->toArray(),
            status: 201,
        );
    }
}
