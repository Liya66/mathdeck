<?php

declare(strict_types=1);

namespace MathDeck\Http\Middleware;

use MathDeck\Http\Exception\Unauthenticated;
use MathDeck\Identity\Exception\AuthenticationFailed;
use MathDeck\Identity\TokenIssuer;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the caller from a signed bearer token.
 *
 * Phases 4 through 7 shipped with the token being the player id verbatim, which
 * authenticated nobody. The boundary it established was the real work; this is the
 * check that now sits behind it, and no endpoint had to change to gain it.
 */
final readonly class IdentityMiddleware implements MiddlewareInterface
{
    public function __construct(private TokenIssuer $tokens)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Bearer ')) {
            throw new Unauthenticated('A bearer token is required.');
        }

        try {
            $token = $this->tokens->verify(trim(substr($header, 7)));
        } catch (AuthenticationFailed $failed) {
            throw new Unauthenticated($failed->getMessage());
        }

        return $handler->handle($request->withAttribute(
            PlayerIdentity::ATTRIBUTE,
            new PlayerIdentity($token->playerId, $token->role),
        ));
    }
}
