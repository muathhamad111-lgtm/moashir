<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\EvidenceNode;
use App\Data\Assessments\BoundSubCriteria;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;

/**
 * Required attachments that came after the request was submitted (decision D-5): the request is past submission and
 * the slot never received a file. A file that was later removed or refused does not make its slot "late".
 */
final class LateAttachmentsReader
{
    /**
     * @return list<string> the attachment keys (unique in the version)
     */
    public function read(Assessment $assessment, BoundSubCriteria $criteria): array
    {
        if ($assessment->submitted_at === null) {
            return [];
        }

        $received = $this->receivedSlotsOf($assessment);
        $late = [];

        foreach ([...$criteria->mains, ...$criteria->subs] as $node) {
            array_push($late, ...$this->lateKeysOf($node, $received));
        }

        return $late;
    }

    /**
     * @param  array<string, true>  $received
     * @return list<string>
     */
    private function lateKeysOf(EvidenceNode $node, array $received): array
    {
        return array_values(array_filter(
            $node->requiredSlotKeys(),
            static fn (string $key): bool => ! isset($received[$node->nodeCode().'|'.$key]),
        ));
    }

    /**
     * @return array<string, true>
     */
    private function receivedSlotsOf(Assessment $assessment): array
    {
        $received = [];

        foreach (EvidenceFile::query()->where('assessment_id', $assessment->id)->whereNotNull('attachment_key')->get(['main_code', 'sub_code', 'attachment_key']) as $file) {
            $received[$file->ownerCode().'|'.$file->attachment_key] = true;
        }

        return $received;
    }
}
