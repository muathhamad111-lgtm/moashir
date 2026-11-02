<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\DiscussionInstruction;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeDiscussionMode;
use App\Enums\Award\CommitteeDiscussionStatus;
use App\Events\Award\CommitteeDiscussionOpened;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\SecretaryGate;
use Illuminate\Support\Facades\DB;

final readonly class OpenCommitteeDiscussion
{
    public function __construct(private SecretaryGate $gate, private AssessmentStateMachine $stateMachine, private AuditRecorder $audit) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, DiscussionInstruction $instruction): CommitteeDiscussion
    {
        return DB::transaction(function () use ($actor, $cycle, $assessment, $instruction): CommitteeDiscussion {
            $locked = $this->gate->enter($actor, $cycle, $assessment, AssessmentVerb::OpenDiscussion->value, $instruction->reason, AdvancedAssessmentStatus::AwaitingSecretaryApproval);
            $isSession = $instruction->mode === CommitteeDiscussionMode::Session;
            $discussion = CommitteeDiscussion::query()->create([
                'award_cycle_id' => $locked->award_cycle_id,
                'assessment_id' => $locked->id,
                'mode' => $instruction->mode,
                'status' => CommitteeDiscussionStatus::Open,
                'deadline_at' => $isSession ? $instruction->deadlineAt : null,
                'agreed_outcome' => $isSession ? null : $instruction->agreedOutcome,
                'opened_by_user_id' => $actor->id,
            ]);
            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::CommitteeDiscussion, AssessmentVerb::OpenDiscussion, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeDiscussionOpened, $locked, ['status' => AdvancedAssessmentStatus::AwaitingSecretaryApproval->value], [
                'status' => AdvancedAssessmentStatus::CommitteeDiscussion->value,
                'discussion_id' => $discussion->id,
                'mode' => $instruction->mode->value,
                'deadline_at' => $discussion->deadline_at?->toIso8601String(),
            ]));

            CommitteeDiscussionOpened::dispatch($discussion, $actor->id);

            return $discussion;
        });
    }
}
