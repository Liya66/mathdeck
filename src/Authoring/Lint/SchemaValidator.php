<?php

declare(strict_types=1);

namespace MathDeck\Authoring\Lint;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;

/**
 * Validates a deck against the published JSON Schema.
 *
 * The same file is served at GET /v1/deck-schema, so the editor describes fields
 * from the identical source the server judges them by. One schema, two readers.
 */
final readonly class SchemaValidator
{
    public const SCHEMA_ID = 'https://mathdeck.dev/schema/deck-v1.schema.json';

    private const MAX_ERRORS = 25;

    public function __construct(private string $schemaPath)
    {
    }

    public static function default(): self
    {
        return new self(dirname(__DIR__, 3) . '/schema/deck-v1.schema.json');
    }

    public function schemaPath(): string
    {
        return $this->schemaPath;
    }

    /**
     * @param array<string, mixed> $deck
     *
     * @return list<array{code: string, path: string, detail: string}>
     */
    public function validate(array $deck): array
    {
        $validator = new Validator();
        $validator->resolver()?->registerFile(self::SCHEMA_ID, $this->schemaPath);

        // Opis reports one error unless told otherwise, and a teacher fixing a deck
        // one problem per save is a bad afternoon. With this raised, every bad field
        // comes back at once — with the caveat that a missing *required* property
        // short-circuits its siblings, so a deck missing a key reports that alone.
        $validator->setMaxErrors(self::MAX_ERRORS);

        $result = $validator->validate(Helper::toJSON($deck), self::SCHEMA_ID);
        $error = $result->error();

        if ($error === null) {
            return [];
        }

        $problems = [];

        foreach ((new ErrorFormatter())->format($error) as $path => $messages) {
            foreach ($messages as $message) {
                $problems[] = [
                    'code' => 'SCHEMA',
                    'path' => $path === '' ? '/' : $path,
                    'detail' => $message,
                ];
            }
        }

        return $problems;
    }
}
