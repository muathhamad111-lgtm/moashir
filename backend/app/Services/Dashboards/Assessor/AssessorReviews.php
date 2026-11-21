<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Assessor;

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Services\Dashboards\AssessmentTotals;
use Illuminate\Database\Eloquent\Builder;

/**
 * The queries behind an assessor's period figures: the reviews they completed and
 * the accredited results those reviews produced.
 */
final readonly class AssessorReviews
{
    public function __construct(private AssessmentTotals $assessments) {}

    /**
     * Assignments this assessor completed, on requests of live entities.
     *
     * @return Builder<AssessmentAssignment>
     */
    public function completed(User $assessor): Builder
    {
        return AssessmentAssignment::query()
            ->where('assessment_assignments.assessor_user_id', $assessor->id)
            ->where('assessment_assignments.status', AssignmentStatus::Completed)
            ->whereIn('assessment_assignments.assessment_id', $this->assessments->operational()->select('assessments.id'));
    }

    /**
     * @return Builder<AssessmentAssignment>
     */
    public function completedWithin(User $assessor, ReportingPeriod $period): Builder
    {
        return $this->completed($assessor)
            ->where('assessment_assignments.ended_at', '>=', $period->start->toIso8601String())
            ->where('assessment_assignments.ended_at', '<', $period->end->toIso8601String());
    }

    /**
     * Accredited requests this assessor reviewed that completed inside the period.
     *
     * @return Builder<Assessment>
     */
    public function accreditedResultsWithin(User $assessor, ReportingPeriod $period): Builder
    {
        return $this->assessments->operational()
            ->reviewedBy($assessor->id)
            ->where('path', AssessmentPath::Accredited)
            ->where('status', AccreditedAssessmentStatus::Completed->value)
            ->where('completed_at', '>=', $period->start->toIso8601String())
            ->where('completed_at', '<', $period->end->toIso8601String());
    }
}
