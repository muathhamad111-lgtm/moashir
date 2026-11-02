<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\ReviewStarted;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\AssignmentRecorder;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class StartReview
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private AssignmentRecorder $assignments,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $reason): Assessment {
            $stages = $assessment->path->reviewStages();
            $locked = $this->gate->enter($actor, $assessment, $stages->underReview, AssessmentVerb::StartReview, $reason);
            $assignment = $this->assignee->assertActsOn($actor, $locked);

            if ($locked->status === $stages->underReview) {
                return $locked;
            }

            if ($locked->status !== $stages->assigned) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::StartReview->value);
            }

            $this->assignments->markStarted($assignment);
            $this->stateMachine->transition($locked, $stages->underReview, AssessmentVerb::StartReview, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentReviewStarted, $locked, ['status' => $stages->assigned->value], [
                'status' => $stages->underReview->value,
                'assignment_id' => $assignment->id,
            ]));

            ReviewStarted::dispatch($locked, $actor->id);

            return $locked;
        });
    }
}
