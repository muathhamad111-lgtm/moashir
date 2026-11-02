<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Award\AdvancedEligibility;
use App\Models\Assessments\Assessment;

final readonly class AdvancedCurrent
{
    public function __construct(public AdvancedEligibility $eligibility, public ?Assessment $request, public ?Assessment $previous) {}
}
