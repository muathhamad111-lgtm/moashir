<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\SecretaryDecisionDraft;
use App\Models\Award\SecretaryDecision;
use App\Models\Identity\User;

final class SecretaryDecisionRecorder
{
    public function record(User $actor, SecretaryDecisionDraft $draft): SecretaryDecision
    {
        return SecretaryDecision::query()->create([
            'award_cycle_id' => $draft->cycleId,
            'assessment_id' => $draft->assessmentId,
            'region_id' => $draft->regionId,
            'kind' => $draft->kind,
            'committee_average_id' => $draft->averageId,
            'committee_assignment_id' => $draft->assignmentId,
            'note' => $draft->note,
            'decided_by_user_id' => $actor->id,
        ]);
    }
}
