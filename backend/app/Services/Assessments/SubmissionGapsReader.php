<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\MissingAttachment;
use App\Data\Assessments\SubmissionGaps;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;

final readonly class SubmissionGapsReader
{
    public function __construct(private CvEntryWriter $cv) {}

    public function read(Assessment $assessment, BoundSubCriteria $subs): SubmissionGaps
    {
        $bodies = $this->cv->bodiesOf($assessment);
        $provided = $this->providedSlots($assessment);
        $missingCv = [];
        $missingAttachments = [];

        foreach ($subs->subs as $sub) {
            if ($sub->hasCv && trim($bodies[$sub->code] ?? '') === '') {
                $missingCv[] = $sub;
            }

            array_push($missingAttachments, ...$this->missingSlotsOf($sub, $provided));
        }

        return new SubmissionGaps($missingCv, $missingAttachments);
    }

    /**
     * @param  array<string, true>  $provided
     * @return list<MissingAttachment>
     */
    private function missingSlotsOf(BoundSub $sub, array $provided): array
    {
        $missing = [];

        foreach ($sub->requiredSlotKeys() as $key) {
            if (! isset($provided[$sub->code.'|'.$key])) {
                $missing[] = new MissingAttachment($sub->id, $sub->code, $key);
            }
        }

        return $missing;
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
