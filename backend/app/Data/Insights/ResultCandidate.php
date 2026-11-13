<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use Carbon\CarbonInterface;
use LogicException;

final readonly class ResultCandidate
{
    public function __construct(
        public Assessment $assessment,
        public ?Certificate $certificate,
        public ?CriteriaVersion $criteriaVersion = null,
    ) {}

    public function id(): int
    {
        return $this->assessment->id;
    }

    public function verifiedAt(): ?CarbonInterface
    {
        return $this->assessment->completed_at;
    }

    public function snapshot(): AssessmentResultSnapshot
    {
        return $this->assessment->currentResult ?? throw new LogicException('A result candidate always carries its current result snapshot.');
    }
}
