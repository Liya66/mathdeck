<?php

declare(strict_types=1);

namespace MathDeck\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Headers every response should carry.
 *
 * Cheap, and the class of bug they prevent is the class you find out about from
 * someone else. `nosniff` matters here specifically: this API returns JSON that
 * contains learner-authored expressions, and a browser that decides to sniff a
 * response as HTML is one step from executing it.
 */
final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        // The API returns no markup, so nothing it sends should ever be rendered.
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
        // Tokens and class data have no business in a shared cache.
        'Cache-Control' => 'no-store',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach (self::HEADERS as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
