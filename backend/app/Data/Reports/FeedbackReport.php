<?php

declare(strict_types=1);

namespace App\Data\Reports;

use App\Data\Assessments\AssessmentResultView;
use App\Models\Certificates\Certificate;

final readonly class FeedbackReport
{
    public function __construct(
        public AssessmentResultView $result,
        public ?Certificate $certificate = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->result->isAvailable();
    }
}
