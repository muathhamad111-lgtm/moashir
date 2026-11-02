<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Actions\Assessments\RebindOpenAssessments;
use App\Events\Criteria\CriteriaEditApplied;
use Illuminate\Contracts\Queue\ShouldQueue;

final class RebindOpenAssessmentsAfterCriteriaEdit implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(private readonly RebindOpenAssessments $rebind) {}

    public function handle(CriteriaEditApplied $event): void
    {
        $this->rebind->handle($event->editEvent, $event->rescoredCodes());
    }
}
