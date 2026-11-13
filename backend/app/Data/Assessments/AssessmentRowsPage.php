<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class AssessmentRowsPage
{
    /**
     * @param  LengthAwarePaginator<int, Assessment|MissedPeriodRow>  $rows
     */
    public function __construct(public LengthAwarePaginator $rows, public int $missedTotal) {}
}
