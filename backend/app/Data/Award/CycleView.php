<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\AwardCycle;

final readonly class CycleView
{
    public function __construct(public AwardCycle $cycle, public bool $revealsStaff, public bool $canManage) {}
}
