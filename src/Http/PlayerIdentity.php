<?php

declare(strict_types=1);

namespace MathDeck\Http;

use MathDeck\Identity\Role;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The authenticated caller.
 *
 * Every command is attributed to this and never to anything in the request body.
 * That is the whole of server authority at the HTTP boundary: a client can say what
 * it wants done, never who is doing it — and as of phase 8 the claim is signed, so
 * it cannot say who it is either.
 */
final readonly class PlayerIdentity
{
    public const ATTRIBUTE = 'playerIdentity';

    public function __construct(
        public string $playerId,
        public Role $role,
    ) {
    }

    public static function of(ServerRequestInterface $request): self
    {
        $identity = $request->getAttribute(self::ATTRIBUTE);

        if (!$identity instanceof self) {
            throw new \LogicException('No identity on the request; the identity middleware did not run.');
        }

        return $identity;
    }
}
