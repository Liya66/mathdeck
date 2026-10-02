<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Application\MatchCreator;
use MathDeck\Application\View\MatchView;
use MathDeck\Http\Exception\BadRequest;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use MathDeck\Http\Request\Body;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/matches
 *
 * Note what the body cannot contain: a seed. A client that picks the seed knows the
 * deck order before a card is dealt.
 */
final readonly class CreateMatchAction
{
    public function __construct(private MatchCreator $creator)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);
        $body = Body::of($request);

        $deckVersionId = $body->string('deckVersionId');
        $playerIds = $body->stringList('playerIds', minimum: 2);

        if (count(array_unique($playerIds)) !== count($playerIds)) {
            throw BadRequest::because('"playerIds" must not repeat a player.');
        }

        // Phase 4 simplification: you can only create a match you are playing in.
        // Teacher-created matches need a role concept, which arrives with real
        // authentication in phase 8.
        if (!in_array($identity->playerId, $playerIds, strict: true)) {
            throw BadRequest::because('You must be one of the players in a match you create.');
        }

        $state = $this->creator->create($deckVersionId, $playerIds);

        return Json::write(
            $response->withHeader('Location', sprintf('/v1/matches/%s', $state->matchId)),
            MatchView::forPlayer($state, $identity->playerId),
            status: 201,
        );
    }
}
