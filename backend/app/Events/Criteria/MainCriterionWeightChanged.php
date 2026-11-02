<?php

declare(strict_types=1);

namespace App\Events\Criteria;

use App\Models\Criteria\MainCriterion;
use App\Support\ValueObjects\Weight;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class MainCriterionWeightChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public MainCriterion $main, public Weight $previousWeight, public ?int $actorId) {}
}
