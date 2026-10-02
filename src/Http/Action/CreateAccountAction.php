<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Http\AccountView;
use MathDeck\Http\Exception\Forbidden;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use MathDeck\Http\Request\Body;
use MathDeck\Identity\AccountService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/accounts — a teacher adds a child to the class.
 *
 * Creates students only. The passcode comes back exactly once, in this response:
 * it is hashed on the way into storage, so nothing can recover it later. A teacher
 * who loses it issues a new one.
 */
final readonly class CreateAccountAction
{
    public function __construct(private AccountService $accounts)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        if (!PlayerIdentity::of($request)->role->mayManageAccounts()) {
            throw Forbidden::because('Only a teacher can add an account.');
        }

        $body = Body::of($request);

        $created = $this->accounts->createStudent(
            $body->string('playerId'),
            $body->string('displayName'),
            $body->optionalString('passcode'),
        );

        return Json::write(
            $response->withHeader('Location', sprintf('/v1/accounts/%s', $created->account->playerId)),
            [
                ...AccountView::of($created->account),
                // Shown once. Write it on a card and hand it to the child.
                'passcode' => $created->passcode,
            ],
            status: 201,
        );
    }
}
