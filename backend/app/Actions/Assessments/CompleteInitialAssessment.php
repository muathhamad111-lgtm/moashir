<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Events\Assessments\InitialAssessmentCompleted;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\InitialAssessmentFinalizer;
use App\Services\Assessments\ReviewCompletenessCheck;
use App\Services\Assessments\ReviewWriteGate;
use Illuminate\Support\Facades\DB;

final readonly class CompleteInitialAssessment
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private ReviewCompletenessCheck $completeness,
        private InitialAssessmentFinalizer $finalizer,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, AdvancedAssessmentStatus::AwaitingFiltering, AssessmentVerb::Complete, $reason);
            $assignment = $this->assignee->assertActsOn($actor, $locked);
            $snapshot = $this->finalizer->finalize($actor, $locked, $assignment, $this->completeness->assertComplete($locked));

            InitialAssessmentCompleted::dispatch($locked, $snapshot, $actor->id);

            return $locked;
        });
    }
}
