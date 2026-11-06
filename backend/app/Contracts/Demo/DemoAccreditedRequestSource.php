<?php

declare(strict_types=1);

namespace App\Contracts\Demo;

use App\Data\Demo\DemoAccreditedResult;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;

interface DemoAccreditedRequestSource
{
    public function completedRequestFor(DemoAccreditedResult $plan, User $assessor): Assessment;
}
