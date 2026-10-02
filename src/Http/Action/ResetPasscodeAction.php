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
 * POST /v1/accounts/{playerId}/passcode — somebody forgot theirs.
 *
 * Issues a new one rather than revealing the old, because the old was never stored.
 * The response also clears that account's sign-in throttle: a child who has just
 * locked themselves out guessing is exactly who this is for.
 */
final readonly class ResetPasscodeAction
{
    public function __construct(
        private AccountService $accounts,
        private \MathDeck\Identity\SignInThrottle $throttle,
    ) {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        if (!PlayerIdentity::of($request)->role->mayManageAccounts()) {
            throw Forbidden::because('Only a teacher can reset a passcode.');
        }

        $issued = $this->accounts->resetPasscode(
            $args['playerId'],
            Body::of($request)->optionalString('passcode'),
        );

        $this->throttle->recordSuccess($args['playerId']);

        return Json::write($response, [
            ...AccountView::of($issued->account),
            'passcode' => $issued->passcode,
        ]);
    }
}
