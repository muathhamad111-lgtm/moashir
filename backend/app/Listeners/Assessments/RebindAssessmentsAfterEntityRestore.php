<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Actions\Assessments\HandleFailedRestoreRebind;
use App\Actions\Assessments\RebindRestoredAssessments;
use App\Events\Entities\EntityRestored;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class RebindAssessmentsAfterEntityRestore implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        private readonly RebindRestoredAssessments $rebind,
        private readonly HandleFailedRestoreRebind $handleFailure,
    ) {}

    public function handle(EntityRestored $event): void
    {
        $this->rebind->handle($event->entity->id);
    }

    public function failed(EntityRestored $event, Throwable $exception): void
    {
        $this->handleFailure->handle($event->entity);
    }
}
