<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\SubmissionCoverage;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;

final readonly class SubmissionCoverageReader
{
    public function __construct(private CvEntryWriter $cv) {}

    public function read(Assessment $assessment): SubmissionCoverage
    {
        return new SubmissionCoverage($this->cv->bodiesOf($assessment), $this->providedSlots($assessment));
    }

    /**
     * @return array<string, true>
     */
    private function providedSlots(Assessment $assessment): array
    {
        $provided = [];
        $evidence = EvidenceFile::query()->where('assessment_id', $assessment->id)->present()->whereNotNull('attachment_key')->with('storedFile')->get();

        foreach ($evidence as $file) {
            if ($file->countsTowardRequirements()) {
                $provided[$file->sub_code.'|'.$file->attachment_key] = true;
            }
        }

        return $provided;
    }
}
