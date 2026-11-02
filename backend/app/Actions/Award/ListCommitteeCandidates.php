<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Award\CandidateRole;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Award\AwardCycleMember;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Award\CycleStaffEligibility;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Contracts\Config\Repository;

final readonly class ListCommitteeCandidates
{
    public function __construct(private CycleStaffEligibility $eligibility, private Repository $config) {}

    /**
     * @return list<User>
     */
    public function handle(AwardCycle $cycle, ?SearchTerm $search): array
    {
        $query = $this->eligibility->candidates(CandidateRole::CommitteeAssessor)
            ->whereNotIn('id', CycleCommitteeAssessor::query()->select('assessor_user_id')->where('award_cycle_id', $cycle->id)->seated())
            ->whereNotIn('id', AwardCycleMember::query()->select('user_id')->where('award_cycle_id', $cycle->id))
            ->whereNotIn('id', AwardCycleAssessor::query()->select('assessor_user_id')->where('award_cycle_id', $cycle->id)->active())
            ->whereNotIn('id', AssessmentAssignment::query()->select('assessor_user_id')->whereIn('assessment_id', Assessment::query()->select('id')->inCycle($cycle->id)))
            ->orderBy('name')
            ->orderBy('id');

        if ($search instanceof SearchTerm) {
            $query->matchingSearch($search);
        }

        return array_values($query->limit($this->config->integer('moashir.award.candidates_limit'))->get()->all());
    }
}
