<?php

declare(strict_types=1);

namespace MathDeck\Engine\Event;

/**
 * Typed reader for a decoded event payload.
 *
 * Lives in the engine, not the persistence layer, because the shape of an event's
 * payload is part of the event's own contract — the class that writes it is the
 * class that reads it back.
 */
final readonly class Payload
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
    }

    public function string(string $key): string
    {
        $value = $this->require($key);

        if (!is_string($value)) {
            throw InvalidPayload::wrongType($key, 'a string');
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidPayload::wrongType($key, 'a string or null');
        }

        return $value;
    }

    public function int(string $key): int
    {
        $value = $this->require($key);

        if (!is_int($value)) {
            throw InvalidPayload::wrongType($key, 'an integer');
        }

        return $value;
    }

    /** @return list<string> */
    public function stringList(string $key): array
    {
        $values = $this->require($key);

        if (!is_array($values)) {
            throw InvalidPayload::wrongType($key, 'a list');
        }

        $strings = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw InvalidPayload::wrongType($key, 'a list of strings');
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /** @return list<array<string, mixed>> */
    public function mapList(string $key): array
    {
        $values = $this->require($key);

        if (!is_array($values)) {
            throw InvalidPayload::wrongType($key, 'a list');
        }

        $maps = [];

        foreach ($values as $value) {
            if (!is_array($value)) {
                throw InvalidPayload::wrongType($key, 'a list of objects');
            }

            /** @var array<string, mixed> $value */
            $maps[] = $value;
        }

        return $maps;
    }

    private function require(string $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw InvalidPayload::missing($key);
        }

        return $this->data[$key];
    }
}
