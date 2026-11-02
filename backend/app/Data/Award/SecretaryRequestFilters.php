<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Support\ValueObjects\SearchTerm;

final readonly class SecretaryRequestFilters
{
    public function __construct(
        public ?AdvancedAssessmentStatus $status,
        public ?int $regionId,
        public ?SearchTerm $search,
        public string $sort,
        public int $perPage,
    ) {}

    public function withoutStatus(): self
    {
        return new self(null, $this->regionId, $this->search, $this->sort, $this->perPage);
    }
}
