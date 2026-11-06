<?php

declare(strict_types=1);

namespace App\Data\Demo;

use App\Enums\Platform\DemoCertificateOutcome;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;

final readonly class DemoAccreditedResult
{
    private const int REVIEW_WEEKS = 3;

    /**
     * @param  list<int>  $profile
     */
    public function __construct(
        public User $owner,
        public array $profile,
        public string $completedOn,
        public DemoCertificateOutcome $certificate,
        public ?string $assessorSummary = null,
    ) {}

    public function completedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->completedOn);
    }

    public function submittedAt(): CarbonImmutable
    {
        return $this->completedAt()->subWeeks(self::REVIEW_WEEKS);
    }

    public function revokesCertificate(): bool
    {
        return $this->certificate === DemoCertificateOutcome::Revoked;
    }

    public function hasAssessorSummary(): bool
    {
        return $this->assessorSummary !== null;
    }
}
