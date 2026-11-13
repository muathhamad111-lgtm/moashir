<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\SubmissionCoverage;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCvEntry;
use App\Models\Assessments\EvidenceFile;

final class SubmissionCoverageReader
{
    public function read(Assessment $assessment): SubmissionCoverage
    {
        return $this->readMany([$assessment->id])[$assessment->id];
    }

    /**
     * @param  list<int>  $assessmentIds
     * @return array<int, SubmissionCoverage>
     */
    public function readMany(array $assessmentIds): array
    {
        $bodies = $this->cvBodiesOf($assessmentIds);
        $slots = $this->providedSlotsOf($assessmentIds);
        $coverage = [];

        foreach ($assessmentIds as $id) {
            $coverage[$id] = new SubmissionCoverage($bodies[$id] ?? [], $slots[$id] ?? []);
        }

        return $coverage;
    }

    /**
     * @param  list<int>  $assessmentIds
     * @return array<int, array<string, string>>
     */
    private function cvBodiesOf(array $assessmentIds): array
    {
        $bodies = [];

        foreach (AssessmentCvEntry::query()->whereIn('assessment_id', $assessmentIds)->get(['assessment_id', 'sub_code', 'body']) as $entry) {
            $bodies[$entry->assessment_id][$entry->sub_code] = $entry->body;
        }

        return $bodies;
    }

    /**
     * @param  list<int>  $assessmentIds
     * @return array<int, array<string, true>>
     */
    private function providedSlotsOf(array $assessmentIds): array
    {
        $provided = [];
        $evidence = EvidenceFile::query()->whereIn('assessment_id', $assessmentIds)->present()->whereNotNull('attachment_key')->with('storedFile')->get();

        foreach ($evidence as $file) {
            if ($file->countsTowardRequirements()) {
                $provided[$file->assessment_id][$file->sub_code.'|'.$file->attachment_key] = true;
            }
        }

        return $provided;
    }
}
