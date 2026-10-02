<?php

declare(strict_types=1);

namespace MathDeck\Http\Request;

use MathDeck\Analytics\ReportFilter;
use MathDeck\Http\Exception\BadRequest;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ReportFilterQuery
{
    public static function from(ServerRequestInterface $request): ReportFilter
    {
        $query = $request->getQueryParams();

        return new ReportFilter(
            deckVersionId: self::text($query, 'deckVersionId'),
            studentKeys: self::list($query, 'students'),
            from: self::moment($query, 'from'),
            to: self::moment($query, 'to'),
        );
    }

    /** @param array<string, mixed> $query */
    private static function text(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return list<string>
     */
    private static function list(array $query, string $key): array
    {
        $value = self::text($query, $key);

        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /** @param array<string, mixed> $query */
    private static function moment(array $query, string $key): ?\DateTimeImmutable
    {
        $value = self::text($query, $key);

        if ($value === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw BadRequest::because(sprintf('"%s" is not a date I can read.', $key));
        }
    }
}
