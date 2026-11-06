<?php

declare(strict_types=1);

namespace App\Data\Demo;

final readonly class DemoInsights
{
    public function __construct(
        public int $accreditedResults,
        public int $revokedCertificates,
        public int $assessorSummaries,
        public int $selfResults,
        public int $maturityTextsFilled,
    ) {}

    public function summary(): string
    {
        return sprintf(
            'accredited results: %d, revoked certificates: %d, assessor summaries: %d, self results: %d, maturity texts filled: %d',
            $this->accreditedResults,
            $this->revokedCertificates,
            $this->assessorSummaries,
            $this->selfResults,
            $this->maturityTextsFilled,
        );
    }
}
