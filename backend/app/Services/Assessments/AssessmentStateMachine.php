<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\AssessmentState;
use App\Enums\Assessments\AssessmentVerb;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Support\RequestContext;

final readonly class AssessmentStateMachine
{
    public function __construct(private RequestContext $context) {}

    public function recordStart(Assessment $assessment, ?int $actorId, AssessmentVerb $verb = AssessmentVerb::Start): void
    {
        $this->record($assessment, null, $assessment->status, $verb, $actorId);
    }

    public function assertAllows(Assessment $assessment, AssessmentState $next, AssessmentVerb $verb): void
    {
        if (! $assessment->status->allowsTransitionTo($next)) {
            throw IllegalStateTransition::from((string) $assessment->status->value, $verb->value);
        }
    }

    public function transition(Assessment $assessment, AssessmentState $next, AssessmentVerb $verb, ?int $actorId): void
    {
        $this->assertAllows($assessment, $next, $verb);

        $previous = $assessment->status;
        $assessment->status = $next;
        $assessment->save();

        $this->record($assessment, $previous, $next, $verb, $actorId);
    }

    public function override(Assessment $assessment, AssessmentState $next, AssessmentVerb $verb, ?int $actorId): void
    {
        $previous = $assessment->status;
        $assessment->status = $next;
        $assessment->save();

        $this->record($assessment, $previous, $next, $verb, $actorId);
    }

    private function record(Assessment $assessment, ?AssessmentState $from, AssessmentState $to, AssessmentVerb $verb, ?int $actorId): void
    {
        AssessmentStatusTransition::query()->create([
            'assessment_id' => $assessment->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'verb' => $verb,
            'actor_user_id' => $actorId,
            'reason' => $this->context->reason(),
        ]);
    }
}
