<?php

declare(strict_types=1);

namespace App\Events\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class InitialAssessmentCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Assessment $assessment, public AssessmentResultSnapshot $snapshot, public int $actorId) {}
}
