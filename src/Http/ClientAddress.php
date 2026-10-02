<?php

declare(strict_types=1);

namespace MathDeck\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The address to throttle against, behind one trusted proxy.
 *
 * REMOTE_ADDR alone is useless here: behind Nginx every request appears to come
 * from the proxy, so one shared bucket would throttle a whole classroom at once.
 *
 * `X-Forwarded-For` is a list and its leftmost entry is whatever the client chose
 * to send, so trusting that would let an attacker mint a fresh bucket per guess.
 * Nginx appends the peer it actually saw (`$proxy_add_x_forwarded_for`), so with a
 * single hop the **last** entry is the one our own infrastructure wrote. That is
 * the one to use. Add another proxy in front and this needs revisiting.
 */
final readonly class ClientAddress
{
    public static function of(ServerRequestInterface $request): ?string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');

        if ($forwarded !== '') {
            $hops = array_values(array_filter(array_map('trim', explode(',', $forwarded))));

            if ($hops !== []) {
                return end($hops);
            }
        }

        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($remote) && $remote !== '' ? $remote : null;
    }
}
