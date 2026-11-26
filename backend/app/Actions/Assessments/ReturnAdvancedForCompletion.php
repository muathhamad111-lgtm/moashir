<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\CompletionInstruction;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AdvancedCompletionReturner;
use App\Services\Assessments\ReviewWriteGate;
use Illuminate\Support\Facades\DB;

final readonly class ReturnAdvancedForCompletion
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AdvancedCompletionReturner $returner,
    ) {}

    public function handle(User $actor, Assessment $assessment, CompletionInstruction $instruction): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $instruction): Assessment {
            $locked = $this->gate->enter($actor, $assessment, AdvancedAssessmentStatus::NeedsCompletion, AssessmentVerb::ReturnForCompletion, $instruction->reason);

            if (! $locked->status instanceof AdvancedAssessmentStatus || ! $locked->status->acceptsCompletionReturn()) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::ReturnForCompletion->value);
            }

            return $this->returner->returnToEntity($actor, $locked, $instruction, AssessmentVerb::ReturnForCompletion);
        });
    }
}
