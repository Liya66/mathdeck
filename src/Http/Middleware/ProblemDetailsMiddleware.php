<?php

declare(strict_types=1);

namespace MathDeck\Http\Middleware;

use MathDeck\Application\Exception\ConcurrencyExhausted;
use MathDeck\Application\Exception\DeckNotFound;
use MathDeck\Application\Exception\MatchNotFound;
use MathDeck\Application\Exception\PlayerNotInMatch;
use MathDeck\Authoring\Exception\DeckNotEditable;
use MathDeck\Authoring\Exception\DeckNotFound as DeckVersionNotFound;
use MathDeck\Authoring\Exception\DeckRejected;
use MathDeck\Engine\Exception\IllegalCommand;
use MathDeck\Http\Exception\BadRequest;
use MathDeck\Http\Exception\Forbidden;
use MathDeck\Http\Exception\Unauthenticated;
use MathDeck\Identity\Exception\AuthenticationFailed;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;

/**
 * Domain failures to RFC 7807 problem documents.
 *
 * The mapping worth arguing about is the one that is not here: a wrong answer is
 * not an error. A learner who plays 3 + 4 against a target of 12 made a perfectly
 * good request, and it returns 200 with a rejection event. Only requests a correct
 * client could not have sent get a 4xx.
 */
final readonly class ProblemDetailsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private ?LoggerInterface $logger = null,
        private bool $debug = false,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $failure) {
            return $this->toProblem($failure);
        }
    }

    private function toProblem(\Throwable $failure): ResponseInterface
    {
        [$status, $title, $detail, $extra] = match (true) {
            $failure instanceof BadRequest => [400, 'Bad request', $failure->getMessage(), []],
            $failure instanceof Unauthenticated => [401, 'Unauthenticated', $failure->getMessage(), []],

            // Signing in failed. The message is deliberately the same whether the
            // account does not exist or the passcode was wrong.
            $failure instanceof AuthenticationFailed => [401, 'Sign-in failed', $failure->getMessage(), []],
            $failure instanceof PlayerNotInMatch => [403, 'Not a player in this match', $failure->getMessage(), []],
            $failure instanceof Forbidden => [403, 'Forbidden', $failure->getMessage(), []],
            $failure instanceof DeckVersionNotFound => [404, 'Deck version not found', $failure->getMessage(), []],

            // Immutability, not a mistake: published decks are forked, never edited.
            $failure instanceof DeckNotEditable => [
                409,
                'Deck is not editable',
                $failure->getMessage(),
                ['reason' => 'DECK_NOT_EDITABLE'],
            ],

            // The publish gate. The whole report goes back so the editor can point
            // at the offending targets rather than just saying no.
            $failure instanceof DeckRejected => [
                422,
                'Deck is not playable',
                $failure->getMessage(),
                ['report' => $failure->report->toArray()],
            ],
            $failure instanceof MatchNotFound => [404, 'Match not found', $failure->getMessage(), []],
            $failure instanceof DeckNotFound => [404, 'Deck version not found', $failure->getMessage(), []],
            $failure instanceof HttpNotFoundException => [404, 'Not found', 'No such resource.', []],
            $failure instanceof HttpMethodNotAllowedException => [405, 'Method not allowed', 'Wrong method.', []],

            // Refused outright: wrong turn, cards not held, match already over.
            $failure instanceof IllegalCommand => [
                409,
                'Command refused',
                $failure->getMessage(),
                ['reason' => $failure->reason->value],
            ],

            // Contention, not a client error. Say so and invite a retry.
            $failure instanceof ConcurrencyExhausted => [
                503,
                'Match is busy',
                $failure->getMessage(),
                ['retryAfter' => 1],
            ],

            default => [500, 'Internal server error', 'Something went wrong.', []],
        };

        if ($status >= 500) {
            $this->logger?->error($failure->getMessage(), ['exception' => $failure]);
        }

        $body = [
            'type' => sprintf('https://mathdeck.dev/problems/%d', $status),
            'title' => $title,
            'status' => $status,
            'detail' => $this->debug || $status < 500 ? $detail : 'Something went wrong.',
            ...$extra,
        ];

        $response = $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/problem+json');

        if ($status === 503) {
            $response = $response->withHeader('Retry-After', '1');
        }

        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR));

        return $response;
    }
}
