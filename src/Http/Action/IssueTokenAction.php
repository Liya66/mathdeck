<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Http\Json;
use MathDeck\Http\Request\Body;
use MathDeck\Identity\Authenticator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/tokens — the only route that does not require a token.
 *
 * Failures are deliberately indistinguishable: "no such account" and "wrong
 * passcode" return the same 401 with the same wording, or the endpoint becomes a
 * way to find out who has an account.
 */
final readonly class IssueTokenAction
{
    public function __construct(private Authenticator $authenticator)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $body = Body::of($request);

        $token = $this->authenticator->signIn($body->string('playerId'), $body->string('passcode'));

        return Json::write($response, [
            'token' => $token->value,
            'playerId' => $token->playerId,
            'role' => $token->role->value,
            'expiresAt' => $token->expiresAt->format('Y-m-d\TH:i:s.up'),
        ]);
    }
}
