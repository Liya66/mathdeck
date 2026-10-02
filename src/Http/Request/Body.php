<?php

declare(strict_types=1);

namespace MathDeck\Http\Request;

use MathDeck\Http\Exception\BadRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Typed reader for a JSON request body.
 *
 * Nothing here can read a player id, because there is no method that would let a
 * caller supply one.
 */
final readonly class Body
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
    }

    public static function of(ServerRequestInterface $request): self
    {
        $parsed = $request->getParsedBody();

        if ($parsed === null) {
            return new self([]);
        }

        if (!is_array($parsed)) {
            throw BadRequest::because('The request body must be a JSON object.');
        }

        /** @var array<string, mixed> $parsed */
        return new self($parsed);
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw BadRequest::because(sprintf('"%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    /** @return list<string> */
    public function stringList(string $key, int $minimum = 0): array
    {
        $values = $this->data[$key] ?? null;

        if (!is_array($values)) {
            throw BadRequest::because(sprintf('"%s" must be an array of strings.', $key));
        }

        $strings = [];

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw BadRequest::because(sprintf('"%s" must contain only non-empty strings.', $key));
            }

            $strings[] = $value;
        }

        if (count($strings) < $minimum) {
            throw BadRequest::because(sprintf('"%s" needs at least %d entries.', $key, $minimum));
        }

        return $strings;
    }
}
