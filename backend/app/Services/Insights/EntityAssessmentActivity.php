<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final class EntityAssessmentActivity
{
    public function openAccreditedRequestId(int $entityId): ?int
    {
        return $this->latestId($this->openAccredited($entityId));
    }

    /**
     * An open request the entity has already sent; an unsent draft is not "in progress".
     */
    public function sentAccreditedRequestId(int $entityId): ?int
    {
        return $this->latestId($this->openAccredited($entityId)->where('status', '!=', AccreditedAssessmentStatus::Draft->value));
    }

    public function hasSelfResult(int $entityId): bool
    {
        return Assessment::query()->ownedBy($entityId)->ofPath(AssessmentPath::SelfAssessment)->exposingResult()->exists();
    }

    /**
     * @return Builder<Assessment>
     */
    private function openAccredited(int $entityId): Builder
    {
        return Assessment::query()
            ->ownedBy($entityId)
            ->ofPath(AssessmentPath::Accredited)
            ->open()
            ->whereNull('current_result_snapshot_id');
    }

    /**
     * @param  Builder<Assessment>  $requests
     */
    private function latestId(Builder $requests): ?int
    {
        $id = $requests->orderByDesc('id')->value('id');

        return $id === null ? null : ScalarValue::int($id);
    }
}
