<?php

declare(strict_types=1);

namespace MathDeck\Http\Action;

use MathDeck\Analytics\ReportFilter;

final readonly class LatencyReportAction extends ReportAction
{
    protected function data(ReportFilter $filter): array
    {
        return $this->reports->latency($filter);
    }
}
