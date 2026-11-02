<?php

declare(strict_types=1);

namespace App\Contracts\Assessments;

interface ReviewedAssessmentState extends AssessmentState
{
    public function isInRequestQueue(): bool;

    public function runsReviewClock(): bool;

    public function acceptsEntityContent(): bool;

    public function hasAssessor(): bool;
}
