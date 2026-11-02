<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\AwardCycle;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SubmissionsClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public AwardCycle $cycle, public ?int $actorId) {}
}
