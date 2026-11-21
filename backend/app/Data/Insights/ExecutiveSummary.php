<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\CertificateStanding;
use App\Models\Assessments\AssessmentSummary;

final readonly class ExecutiveSummary
{
    public function __construct(
        public InsightsSubject $subject,
        public StrengthsGaps $classes,
        public MaturityProfile $profile,
        public ResultDelta $delta,
        public ExecutiveHighlights $highlights,
        public CertificateStanding $standing,
        public ?AssessmentSummary $assessorSummary,
    ) {}
}
