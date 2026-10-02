<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\DeckService;
use MathDeck\Authoring\DeckStatus;
use MathDeck\Http\Exception\Forbidden;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /v1/decks/{deckVersionId} */
final readonly class GetDeckAction
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
        $version = $this->decks->get($args['deckVersionId']);

        if ($version->status !== DeckStatus::Published && $version->authorId !== $identity->playerId) {
            throw Forbidden::because('That draft belongs to someone else.');
        }

        return Json::write($response, $version->toArray());
    }
}
