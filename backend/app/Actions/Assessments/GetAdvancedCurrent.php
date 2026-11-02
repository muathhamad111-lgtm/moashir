<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AdvancedCurrent;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Award\AdvancedEligibilityChecker;
use App\Services\Award\CurrentCycleReader;
use Illuminate\Support\Facades\Date;

final readonly class GetAdvancedCurrent
{
    private const array RELATIONS = ['entity', 'currentResult', 'awardCycle'];

    public function __construct(private CurrentCycleReader $cycles, private AdvancedEligibilityChecker $eligibility) {}

    public function handle(User $actor): AdvancedCurrent
    {
        $entity = Entity::withTrashed()->find($actor->entity_id) ?? throw AssessmentRejected::entityRequired();
        $cycle = $this->cycles->running();

        return new AdvancedCurrent(
            $this->eligibility->check($entity, $cycle, Date::now()),
            $cycle instanceof AwardCycle ? $this->requestIn($entity, $cycle) : null,
            $this->previousRequest($entity, $cycle),
        );
    }

    private function requestIn(Entity $entity, AwardCycle $cycle): ?Assessment
    {
        return Assessment::query()
            ->with(self::RELATIONS)
            ->ownedBy($entity->id)
            ->inCycle($cycle->id)
            ->orderByRaw('case when status = ? then 1 else 0 end', [AdvancedAssessmentStatus::RejectedConditions->value])
            ->latest('id')
            ->first();
    }

    private function previousRequest(Entity $entity, ?AwardCycle $current): ?Assessment
    {
        $query = Assessment::query()
            ->with(self::RELATIONS)
            ->ownedBy($entity->id)
            ->ofPath(AssessmentPath::Advanced)
            ->whereNot('status', AdvancedAssessmentStatus::RejectedConditions);

        if ($current instanceof AwardCycle) {
            $query->where('award_cycle_id', '<>', $current->id);
        }

        return $query->latest('id')->first();
    }
}
