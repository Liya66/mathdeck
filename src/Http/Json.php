<?php

declare(strict_types=1);

namespace MathDeck\Http;

use Psr\Http\Message\ResponseInterface;

final readonly class Json
{
    /**
     * JSON_PRESERVE_ZERO_FRACTION matters more than it looks: without it an
     * accuracy of exactly 1.0 encodes as `1`, and a client that type-checks its
     * numbers sees an integer where the spec promises a number — but only for
     * classes that happen to get everything right.
     *
     * @param array<string, mixed> $body
     */
    public static function write(ResponseInterface $response, array $body, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
