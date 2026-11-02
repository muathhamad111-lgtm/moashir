<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Support\ValueObjects\SearchTerm;

final readonly class RequestQueueFilters
{
    public function __construct(
        public ?AssessmentPath $path = null,
        public ?string $status = null,
        public ?bool $overdue = null,
        public ?int $assessorId = null,
        public ?int $cycleId = null,
        public ?SearchTerm $search = null,
        public string $sort = '-submitted_at',
        public int $perPage = 15,
    ) {}

    /**
     * @return list<AssessmentPath>
     */
    public function paths(): array
    {
        return $this->path instanceof AssessmentPath ? [$this->path] : AssessmentPath::reviewed();
    }
}
