<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AdvancedDraftEraser;
use App\Services\Assessments\AssessmentEntityGuard;
use App\Services\Assessments\AssessmentOverrideReason;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class DeleteAdvancedDraft
{
    public function __construct(
        private AssessmentEntityGuard $entityGuard,
        private AssessmentRowLock $rowLock,
        private AssessmentOverrideReason $overrideReason,
        private AdvancedDraftEraser $eraser,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?string $reason): void
    {
        $this->entityGuard->assertMayAct($assessment->entity);

        DB::transaction(function () use ($actor, $assessment, $reason): void {
            $locked = $this->rowLock->lock($assessment->id);
            $this->overrideReason->requireFor($actor, $locked, $reason);

            if ($locked->status !== AdvancedAssessmentStatus::Draft) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::SaveDraft->value);
            }

            $this->audit->record(new AuditEntry(AuditEvent::AwardDraftDeleted, $locked, ['status' => AdvancedAssessmentStatus::Draft->value], [
                'request_number' => $locked->request_number,
                'award_cycle_id' => $locked->award_cycle_id,
            ]));
            $this->eraser->erase($locked);
        });
    }
}
