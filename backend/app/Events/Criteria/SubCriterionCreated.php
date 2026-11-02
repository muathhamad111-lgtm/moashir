<?php

declare(strict_types=1);

namespace App\Events\Criteria;

use App\Models\Criteria\SubCriterion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SubCriterionCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public SubCriterion $sub, public ?int $actorId) {}
}
