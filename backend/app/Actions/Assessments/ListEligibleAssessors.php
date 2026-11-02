<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\EligibleAssessor;
use App\Enums\Assessments\AssignmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessorEligibility;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SearchTerm;

final readonly class ListEligibleAssessors
{
    private const int LIMIT = 100;

    public function __construct(private AssessorEligibility $eligibility) {}

    /**
     * @return list<EligibleAssessor>
     */
    public function handle(User $actor, ?Assessment $assessment, ?SearchTerm $search): array
    {
        $query = $this->eligibility->eligibleFor($actor, $assessment)
            ->select(['id', 'name'])
            ->selectSub(AssessmentAssignment::query()
                ->selectRaw('count(*)')
                ->whereColumn('assessor_user_id', 'users.id')
                ->where('status', AssignmentStatus::Active), 'open_requests')
            ->orderBy('name')
            ->limit(self::LIMIT);

        if ($search instanceof SearchTerm) {
            $query->matchingSearch($search);
        }

        $currentAssessorId = $assessment?->loadMissing('activeAssignment')->activeAssignment?->assessor_user_id;

        if ($currentAssessorId !== null) {
            $query->whereKeyNot($currentAssessorId);
        }

        return array_values($query->get()->map(static fn (User $user): EligibleAssessor => new EligibleAssessor(
            $user->id,
            $user->name,
            ScalarValue::int($user->getAttribute('open_requests')),
        ))->all());
    }
}
