<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Assessments\Assessment;
use App\Models\Award\FilteringDecision;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EntityNotQualified implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Assessment $assessment, public FilteringDecision $decision, public int $actorId) {}
}
