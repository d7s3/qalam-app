<?php

namespace App\Services\Reports\Concerns;

use App\Services\Reports\ReportQuery;

/**
 * The four ways a report about students may be gathered.
 */
trait GroupsByStudent
{
    /** @return array<string, string> */
    public function groupings(): array
    {
        return ReportQuery::groupings();
    }
}
