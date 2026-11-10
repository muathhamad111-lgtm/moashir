<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Periods\PeriodFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Support\ValueObjects\SearchTerm;

final readonly class PreparingRequestFilters
{
    public function __construct(
        public AssessmentPath $path,
        public ?int $cycleId = null,
        public ?SearchTerm $search = null,
        public string $sort = 'created_at',
        public int $perPage = 15,
        public ?PeriodFilter $period = null,
    ) {}
}
