<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Http\ClientAddress;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use MathDeck\Http\Request\Body;
use MathDeck\Identity\AccountService;
use MathDeck\Identity\SignInThrottle;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PUT /v1/accounts/me/passcode — changing your own.
 *
 * Requires the current passcode, and is throttled on the same buckets as signing
 * in: this endpoint checks a credential, so without that it would simply be an
 * unthrottled way to guess one.
 */
final readonly class ChangeOwnPasscodeAction
{
    public function __construct(
        private AccountService $accounts,
        private SignInThrottle $throttle,
    ) {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);
        $address = ClientAddress::of($request);
        $body = Body::of($request);

        $this->throttle->check($identity->playerId, $address);

        try {
            $this->accounts->changeOwnPasscode(
                $identity->playerId,
                $body->string('currentPasscode'),
                $body->string('newPasscode'),
            );
        } catch (\MathDeck\Identity\Exception\AuthenticationFailed $wrong) {
            $this->throttle->recordFailure($identity->playerId, $address);

            throw $wrong;
        }

        $this->throttle->recordSuccess($identity->playerId);

        return Json::write($response, ['changed' => true]);
    }
}
