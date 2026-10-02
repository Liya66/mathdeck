<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\DeckService;
use MathDeck\Http\Exception\BadRequest;
use MathDeck\Http\Exception\Forbidden;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PUT /v1/decks/{deckVersionId}
 *
 * Only drafts, and only your own. A published deck answers 409 with the advice to
 * fork instead.
 */
final readonly class UpdateDeckAction
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

        if (!is_array($body) || !is_array($body['definition'] ?? null)) {
            throw BadRequest::because('"definition" must be an object.');
        }

        if ($this->decks->get($args['deckVersionId'])->authorId !== $identity->playerId) {
            throw Forbidden::because('That deck belongs to someone else. Fork it to make your own version.');
        }

        /** @var array<string, mixed> $definition */
        $definition = $body['definition'];

        return Json::write($response, $this->decks->updateDraft($args['deckVersionId'], $definition)->toArray());
    }
}
