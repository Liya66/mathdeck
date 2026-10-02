<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\DeckService;
use MathDeck\Http\Exception\BadRequest;
use MathDeck\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/decks/lint
 *
 * Lints a definition without saving it, so the editor can tell a teacher that a
 * target has become unreachable while they are still typing — rather than at the
 * moment they try to publish.
 *
 * Always 200: a deck full of problems is a successful analysis of a broken deck.
 */
final readonly class LintDeckAction
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
        $body = $request->getParsedBody();

        if (!is_array($body) || !is_array($body['definition'] ?? null)) {
            throw BadRequest::because('"definition" must be an object.');
        }

        /** @var array<string, mixed> $definition */
        $definition = $body['definition'];

        return Json::write($response, $this->decks->lintDefinition($definition)->toArray());
    }
}
