<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Http\AccountView;
use MathDeck\Http\Exception\Forbidden;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use MathDeck\Identity\Account;
use MathDeck\Identity\AccountService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /v1/accounts — the class list. */
final readonly class ListAccountsAction
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
            throw Forbidden::because('Only a teacher can see the class list.');
        }

        return Json::write($response, [
            'accounts' => array_map(
                static fn (Account $account): array => AccountView::of($account),
                $this->accounts->all(),
            ),
        ]);
    }
}
