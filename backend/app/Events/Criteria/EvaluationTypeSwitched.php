<?php

declare(strict_types=1);

namespace App\Events\Criteria;

use App\Models\Criteria\SubCriterion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EvaluationTypeSwitched implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $removedPointCodes
     */
    public function __construct(public SubCriterion $sub, public array $removedPointCodes, public ?int $actorId) {}
}
