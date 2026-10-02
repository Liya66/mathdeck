<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Application\MatchEventReader;
use MathDeck\Application\StoredEvent;
use MathDeck\Application\View\EventView;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /v1/matches/{matchId}/events?since=N
 *
 * The client's cursor into the log. Every event is redacted for the caller before
 * it leaves: this endpoint is the one most likely to leak, because it is the one
 * that hands over raw history.
 */
final readonly class GetEventsAction
{
    public function __construct(private MatchEventReader $reader)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);
        $query = $request->getQueryParams();
        $since = isset($query['since']) && is_numeric($query['since']) ? (int) $query['since'] : 0;

        $events = $this->reader->since($args['matchId'], $identity->playerId, $since);

        return Json::write($response, [
            'since' => $since,
            'events' => array_map(
                static fn (StoredEvent $stored): array => [
                    'seq' => $stored->seq,
                    ...EventView::forPlayer($stored->event, $identity->playerId),
                ],
                $events,
            ),
        ]);
    }
}
