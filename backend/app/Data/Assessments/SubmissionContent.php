<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class SubmissionContent
{
    public function __construct(public BoundSubCriteria $subs, public CvSheet $cv, public EvidenceListing $evidence) {}
}
