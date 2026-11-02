<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Award\AdvancedEligibility;
use App\Models\Assessments\Assessment;

final readonly class AdvancedApplication
{
    public function __construct(public Assessment $assessment, public bool $created, public ?AdvancedEligibility $rejection = null) {}

    public function isRejected(): bool
    {
        return $this->rejection instanceof AdvancedEligibility;
    }
}
