<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Events\Assessments\AssessmentCompleted;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AccreditedResultFinalizer;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\ReviewCompletenessCheck;
use App\Services\Assessments\ReviewWriteGate;
use Illuminate\Support\Facades\DB;

final readonly class CompleteAccreditedAssessment
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private ReviewCompletenessCheck $completeness,
        private AccreditedResultFinalizer $finalizer,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, AccreditedAssessmentStatus::Completed, AssessmentVerb::Complete, $reason);
            $assignment = $this->assignee->assertActsOn($actor, $locked);

            if ($locked->awaitsCriteriaUpdate()) {
                throw AssessmentRejected::criteriaUpdateInProgress();
            }

            $snapshot = $this->finalizer->finalize($actor, $locked, $assignment, $this->completeness->assertComplete($locked));

            AssessmentCompleted::dispatch($locked, $snapshot);

            return $locked;
        });
    }
}
