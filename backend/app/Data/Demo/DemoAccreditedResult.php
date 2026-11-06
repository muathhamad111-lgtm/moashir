<?php

declare(strict_types=1);

namespace App\Data\Demo;

use App\Enums\Platform\DemoCertificateOutcome;

final readonly class DemoAccreditedResult
{
    /**
     * @param  list<int>  $profile
     */
    public function __construct(
        public array $profile,
        public string $completedOn,
        public DemoCertificateOutcome $certificate,
        public ?string $assessorSummary = null,
    ) {}
}
