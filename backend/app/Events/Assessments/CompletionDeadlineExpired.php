<?php

declare(strict_types=1);

namespace App\Events\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CompletionDeadlineExpired implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Assessment $assessment, public AssessmentCompletionRequest $completionRequest, public ?int $assessorId) {}
}
