<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Enums\Periods\EntityPeriodState;
use App\Models\Assessments\Assessment;

final readonly class ResolvedPeriodState
{
    public function __construct(public EntityPeriodState $state, public ?Assessment $request = null) {}
}
