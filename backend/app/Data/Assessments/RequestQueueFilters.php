<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Periods\PeriodFilter;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\QueueStage;
use App\Support\ValueObjects\SearchTerm;

final readonly class RequestQueueFilters
{
    public function __construct(
        public AssessmentPath $path,
        public ?string $status = null,
        public ?bool $overdue = null,
        public ?int $assessorId = null,
        public ?int $cycleId = null,
        public ?SearchTerm $search = null,
        public string $sort = '-submitted_at',
        public int $perPage = 15,
        public ?PeriodFilter $period = null,
        public QueueStage $stage = QueueStage::All,
    ) {}

    /**
     * The same filters over every stage: the stage counts read from it.
     */
    public function acrossStages(): self
    {
        return new self($this->path, $this->status, $this->overdue, $this->assessorId, $this->cycleId, $this->search, $this->sort, $this->perPage, $this->period);
    }
}
