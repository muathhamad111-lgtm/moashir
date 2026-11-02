<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeDiscussionStatus;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\SecretaryGate;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CloseCommitteeDiscussion
{
    public function __construct(private SecretaryGate $gate, private AssessmentStateMachine $stateMachine, private AuditRecorder $audit) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, CommitteeDiscussion $discussion, ?string $summary, ?string $reason): CommitteeDiscussion
    {
        return DB::transaction(function () use ($actor, $cycle, $assessment, $discussion, $summary, $reason): CommitteeDiscussion {
            $locked = $this->gate->enter($actor, $cycle, $assessment, AssessmentVerb::CloseDiscussion->value, $reason, AdvancedAssessmentStatus::CommitteeDiscussion);
            $open = CommitteeDiscussion::query()->whereKey($discussion->id)->lockForUpdate()->firstOrFail();

            if (! $open->isOpen()) {
                throw CommitteeRejected::discussionClosed();
            }

            $open->forceFill(['status' => CommitteeDiscussionStatus::Closed, 'summary' => $summary, 'closed_by_user_id' => $actor->id, 'closed_at' => Date::now()])->save();
            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::AwaitingSecretaryApproval, AssessmentVerb::CloseDiscussion, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeDiscussionClosed, $locked, ['status' => AdvancedAssessmentStatus::CommitteeDiscussion->value], [
                'status' => AdvancedAssessmentStatus::AwaitingSecretaryApproval->value,
                'discussion_id' => $open->id,
            ]));

            return $open;
        });
    }
}
