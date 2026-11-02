<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CvSheet;
use App\Data\Assessments\EvidenceListing;
use App\Data\Assessments\SubmissionContent;
use App\Models\Assessments\Assessment;

final readonly class SubmissionContentReader
{
    public function __construct(private BoundSubCriteriaReader $subs, private CvEntryWriter $cv, private EvidenceReader $evidence) {}

    public function forOwner(Assessment $assessment): SubmissionContent
    {
        $subs = $this->subs->of($assessment->criteriaVersion);

        return new SubmissionContent($subs, new CvSheet($assessment, $subs, $this->cv->bodiesOf($assessment)), new EvidenceListing($this->evidence->forOwner($assessment), $subs));
    }

    public function forReviewer(Assessment $assessment): SubmissionContent
    {
        $subs = $this->subs->of($assessment->criteriaVersion);

        return new SubmissionContent($subs, new CvSheet($assessment, $subs, $this->cv->bodiesOf($assessment)), new EvidenceListing($this->evidence->cleanOnly($assessment), $subs));
    }
}
