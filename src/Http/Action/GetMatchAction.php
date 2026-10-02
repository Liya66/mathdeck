<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Application\MatchRepository;
use MathDeck\Application\View\MatchView;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /v1/matches/{matchId} */
final readonly class GetMatchAction
{
    public function __construct(private MatchRepository $repository)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);
        $state = $this->repository->load($args['matchId']);

        return Json::write($response, MatchView::forPlayer($state, $identity->playerId));
    }
}
