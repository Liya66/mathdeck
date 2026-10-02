<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Analytics\ReportFilter;

final readonly class ErrorsReportAction extends ReportAction
{
    protected function data(ReportFilter $filter): array
    {
        return $this->reports->errorDistribution($filter);
    }
}
