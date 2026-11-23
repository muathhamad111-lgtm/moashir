<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Enums\Assessments\AssessmentVerb;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AccreditedCompletionRequester;
use App\Services\Assessments\AdvancedCompletionReturner;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\ReviewCompletenessCheck;
use App\Services\Assessments\ReviewWriteGate;
use Illuminate\Support\Facades\DB;

final readonly class RequestCompletion
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private ReviewCompletenessCheck $completeness,
        private AccreditedCompletionRequester $accredited,
        private AdvancedCompletionReturner $advanced,
    ) {}

    public function handle(User $actor, Assessment $assessment, CompletionInstruction $instruction): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $instruction): Assessment {
            $stages = $assessment->path->reviewStages();
            $locked = $this->gate->enter($actor, $assessment, $stages->needsCompletion, AssessmentVerb::RequestCompletion, $instruction->reason);
            $this->assignee->assertActsOn($actor, $locked);

            if ($locked->status !== $stages->underReview) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::RequestCompletion->value);
            }

            $this->completeness->assertComplete($locked);

            return $locked->isAdvanced()
                ? $this->advanced->returnToEntity($actor, $locked, $instruction, AssessmentVerb::RequestCompletion)
                : $this->accredited->request($actor, $locked, $instruction);
        });
    }
}
