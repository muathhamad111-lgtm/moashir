<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\MissingAttachment;
use App\Data\Assessments\SubmissionGaps;
use App\Models\Assessments\Assessment;

final readonly class SubmissionGapsReader
{
    public function __construct(private SubmissionCoverageReader $coverage) {}

    public function read(Assessment $assessment, BoundSubCriteria $subs): SubmissionGaps
    {
        $coverage = $this->coverage->read($assessment);
        $missingCv = [];
        $missingAttachments = [];

        foreach ($subs->subs as $sub) {
            if ($coverage->lacksCv($sub)) {
                $missingCv[] = $sub;
            }

            foreach ($coverage->missingSlotKeysOf($sub) as $key) {
                $missingAttachments[] = new MissingAttachment($sub->id, $sub->code, $key);
            }
        }

        return new SubmissionGaps($missingCv, $missingAttachments);
    }
}
