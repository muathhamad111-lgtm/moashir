<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Entities\EntityErasureStep;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Assessments\AssessmentCvEntry;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Award\FilteringDecision;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Support\ScalarValue;

final class ReviewedRequestEraser implements EntityErasureStep
{
    public function erase(Entity $entity): void
    {
        $requests = Assessment::query()->ownedBy($entity->id)->whereIn('path', AssessmentPath::reviewed());
        $requestIds = $this->idsOf((clone $requests)->pluck('id')->all());

        if ($requestIds === []) {
            return;
        }

        $this->detachFiles(EvidenceFile::query()->whereIn('assessment_id', $requestIds)->pluck('stored_file_id')->all());
        $this->detachFiles(EvidenceBundle::query()->whereIn('assessment_id', $requestIds)->whereNotNull('stored_file_id')->pluck('stored_file_id')->all());

        EvidenceFile::query()->whereIn('assessment_id', $requestIds)->delete();
        EvidenceBundle::query()->whereIn('assessment_id', $requestIds)->delete();
        AssessmentCvEntry::query()->whereIn('assessment_id', $requestIds)->delete();
        AssessmentCompletionRequest::query()->whereIn('assessment_id', $requestIds)->delete();
        FilteringDecision::query()->whereIn('assessment_id', (clone $requests)->open()->select('id'))->toBase()->delete();

        (clone $requests)->open()->delete();
    }

    /**
     * @param  array<array-key, mixed>  $fileIds
     */
    private function detachFiles(array $fileIds): void
    {
        $ids = $this->idsOf($fileIds);

        if ($ids !== []) {
            StoredFile::withTrashed()->whereKey($ids)->update(['attachable_type' => null, 'attachable_id' => null]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<int>
     */
    private function idsOf(array $values): array
    {
        return array_values(array_map(static fn (mixed $id): int => ScalarValue::int($id), $values));
    }
}
