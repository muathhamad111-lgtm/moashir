<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\AssessmentPath;

final readonly class AssessmentListFilters
{
    public function __construct(
        public ?AssessmentPath $path = null,
        public ?string $status = null,
        public ?int $entityId = null,
        public string $sort = '-created_at',
        public int $perPage = 15,
        public ?bool $isOpen = null,
        public ?bool $hasResult = null,
    ) {}
}
