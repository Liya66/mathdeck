<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\DeckService;
use MathDeck\Authoring\DeckStatus;
use MathDeck\Authoring\DeckVersion;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /v1/decks
 *
 * Published decks are public — a teacher should be able to build on a colleague's
 * work. Drafts are private to their author, because an unpublished deck is usually
 * a broken one.
 */
final readonly class ListDecksAction
{
    public function __construct(private DeckService $decks)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);

        $visible = array_values(array_filter(
            $this->decks->all(),
            static fn (DeckVersion $version): bool => $version->status === DeckStatus::Published
                || $version->authorId === $identity->playerId,
        ));

        return Json::write($response, [
            'decks' => array_map(
                static fn (DeckVersion $version): array => [
                    'deckVersionId' => $version->id(),
                    'deckId' => $version->deckId,
                    'version' => $version->version,
                    'name' => $version->name(),
                    'authorId' => $version->authorId,
                    'status' => $version->status->value,
                    'updatedAt' => $version->updatedAt->format('Y-m-d\TH:i:s.up'),
                ],
                $visible,
            ),
        ]);
    }
}
