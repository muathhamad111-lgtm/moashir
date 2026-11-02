<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Assessments\Assessment;
use App\Models\Award\SecretaryDecision;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class ReturnedToCommittee implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Assessment $assessment, public SecretaryDecision $decision, public int $actorId) {}
}
