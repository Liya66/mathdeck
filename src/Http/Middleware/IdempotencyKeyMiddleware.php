<?php

declare(strict_types=1);

namespace MathDeck\Http\Middleware;

use MathDeck\Http\Exception\BadRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Requires an Idempotency-Key on state-changing requests and hands it to the
 * application as the client command id.
 *
 * Mandatory rather than optional on purpose. The storage for it already exists;
 * making it optional would mean a client that forgets can double-play a hand on a
 * retry, and the class of bug that produces is very hard to see from the logs.
 *
 * Applied per route rather than to every POST, so a route only demands the header
 * once it can actually honour it. Both writes that create something now do:
 * commands since phase 4, match creation since phase 8.
 */
final readonly class IdempotencyKeyMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'clientCommandId';
    public const HEADER = 'Idempotency-Key';

    private const MAX_LENGTH = 64;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = trim($request->getHeaderLine(self::HEADER));

        if ($key === '') {
            throw BadRequest::because(sprintf('An %s header is required on this request.', self::HEADER));
        }

        if (strlen($key) > self::MAX_LENGTH) {
            throw BadRequest::because(sprintf('%s must be at most %d characters.', self::HEADER, self::MAX_LENGTH));
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $key));
    }
}
