<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;

final readonly class CertificateIssue
{
    public function __construct(
        public Assessment $assessment,
        public AssessmentResultSnapshot $result,
        public ?int $issuedByUserId,
        public ?Certificate $replaces = null,
        public ?string $reissueReason = null,
    ) {}
}
