<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Analytics\ReportFilter;
use MathDeck\Http\Exception\Forbidden;
use MathDeck\Http\Json;
use MathDeck\Http\PlayerIdentity;
use MathDeck\Http\Request\ReportFilterQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shared shape for the teacher reports.
 *
 * Every one of these exposes one person's performance to another, so the check
 * lives here rather than in each action — a report added later inherits the guard
 * instead of having to remember it. The role is read from the signed token, so it
 * is not something a caller can assert about themselves.
 */
abstract readonly class ReportAction
{
    public function __construct(
        protected ReportQueries $reports,
    ) {
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    abstract protected function data(ReportFilter $filter): array;

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $identity = PlayerIdentity::of($request);

        if (!$identity->role->mayReadClassReports()) {
            throw Forbidden::because('Class reports are for teachers.');
        }

        $filter = ReportFilterQuery::from($request);

        return Json::write($response, [
            'filter' => $filter->toArray(),
            'data' => $this->data($filter),
        ]);
    }
}
