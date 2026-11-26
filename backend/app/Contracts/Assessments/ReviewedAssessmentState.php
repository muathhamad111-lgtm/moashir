<?php

declare(strict_types=1);

namespace App\Contracts\Assessments;

interface ReviewedAssessmentState extends AssessmentState
{
    public function isInRequestQueue(): bool;

    public function isPreparedByEntity(): bool;

    public function runsReviewClock(): bool;

    public function acceptsEntityContent(): bool;

    public function hasAssessor(): bool;

    /**
     * Whether the follower may hand the request to another assessor in this state. The request still needs an
     * active assignment to hand over.
     */
    public function acceptsReassignment(): bool;
}
