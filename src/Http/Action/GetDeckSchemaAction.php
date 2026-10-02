<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Authoring\Lint\SchemaValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /v1/deck-schema
 *
 * Serves the exact file the server validates against, so the editor describes
 * fields from the same source that judges them. One schema, two readers.
 */
final readonly class GetDeckSchemaAction
{
    public function __construct(private SchemaValidator $validator)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $response->getBody()->write((string) file_get_contents($this->validator->schemaPath()));

        return $response->withHeader('Content-Type', 'application/schema+json');
    }
}
