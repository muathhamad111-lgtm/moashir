<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\QualificationCandidates;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;

final readonly class QualificationCandidateFinder
{
    public function __construct(private FilteringDecisionRecorder $decisions) {}

    public function of(AwardCycle $cycle, User $actor): QualificationCandidates
    {
        $awaiting = Assessment::query()->inCycle($cycle->id)
            ->where('status', AdvancedAssessmentStatus::AwaitingFiltering)
            // The relations the single qualify path gets from the `assessment` route binding.
            ->with(['entity', 'criteriaVersion', 'currentResult'])
            ->orderBy('id')
            ->get();
        $reaching = $awaiting->filter(fn (Assessment $assessment): bool => $this->reachesThreshold($assessment, $cycle));
        $own = $this->assessedBy($actor, array_values($reaching->map(static fn (Assessment $assessment): int => $assessment->id)->all()));
        $eligible = $reaching->reject(static fn (Assessment $assessment): bool => in_array($assessment->id, $own, true));

        return new QualificationCandidates(array_values($eligible->all()), count($own));
    }

    private function reachesThreshold(Assessment $assessment, AwardCycle $cycle): bool
    {
        return $assessment->currentResult instanceof AssessmentResultSnapshot
            && ! $this->decisions->isBelow($assessment->currentResult->percent, $cycle);
    }

    /**
     * @param  list<int>  $assessmentIds
     * @return list<int>
     */
    private function assessedBy(User $actor, array $assessmentIds): array
    {
        if ($actor->isSuperAdmin() || $assessmentIds === []) {
            return [];
        }

        $assessed = AssessmentAssignment::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->where('assessor_user_id', $actor->id)
            ->get(['assessment_id'])
            ->map(static fn (AssessmentAssignment $assignment): int => $assignment->assessment_id);

        return array_values(array_unique($assessed->all()));
    }
}
