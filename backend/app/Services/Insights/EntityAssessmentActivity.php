<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Support\ScalarValue;

final class EntityAssessmentActivity
{
    public function openAccreditedRequestId(int $entityId): ?int
    {
        $id = Assessment::query()
            ->ownedBy($entityId)
            ->ofPath(AssessmentPath::Accredited)
            ->open()
            ->whereNull('current_result_snapshot_id')
            ->orderByDesc('id')
            ->value('id');

        return $id === null ? null : ScalarValue::int($id);
    }

    public function hasSelfResult(int $entityId): bool
    {
        return Assessment::query()->ownedBy($entityId)->ofPath(AssessmentPath::SelfAssessment)->exposingResult()->exists();
    }
}
