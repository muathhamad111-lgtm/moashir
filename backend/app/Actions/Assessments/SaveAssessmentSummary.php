<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentSummaryChange;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentSummaryReader;
use App\Services\Assessments\AssessmentSummaryWriter;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SaveAssessmentSummary
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private AssessmentSummaryWriter $writer,
        private AssessmentSummaryReader $reader,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?string $summary, ?string $reason): ?AssessmentSummary
    {
        if (! $assessment->isAccredited()) {
            throw AssessmentRejected::summaryNotApplicable();
        }

        return DB::transaction(function () use ($actor, $assessment, $summary, $reason): ?AssessmentSummary {
            $underReview = AccreditedAssessmentStatus::UnderReview;
            $locked = $this->gate->enter($actor, $assessment, $underReview, AssessmentVerb::ScoreReview, $reason);
            $this->assignee->assertActsOn($actor, $locked);

            if ($locked->status !== $underReview) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::ScoreReview->value);
            }

            $change = $this->writer->write($locked, $summary ?? '', $actor);

            if ($change instanceof AssessmentSummaryChange) {
                $locked->touch();
                $this->audit->record(new AuditEntry(AuditEvent::AssessmentSummarySaved, $locked, [], [
                    'version' => $change->version,
                    'cleared' => $change->cleared,
                ]));
            }

            return $this->reader->currentOf($locked);
        });
    }
}
