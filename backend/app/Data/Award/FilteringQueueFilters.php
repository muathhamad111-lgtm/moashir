<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Support\ValueObjects\SearchTerm;

final readonly class FilteringQueueFilters
{
    public function __construct(
        public ?AdvancedAssessmentStatus $status = null,
        public ?int $regionId = null,
        public ?SearchTerm $search = null,
        public string $sort = '-initial_percent',
        public int $perPage = 15,
    ) {}

    public function withoutStatus(): self
    {
        return new self(null, $this->regionId, $this->search, $this->sort, $this->perPage);
    }
}
