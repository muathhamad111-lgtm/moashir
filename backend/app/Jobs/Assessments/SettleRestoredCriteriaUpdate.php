<?php

declare(strict_types=1);

namespace App\Jobs\Assessments;

use App\Actions\Assessments\HandleFailedRestoreRebind;
use App\Actions\Assessments\RebindRestoredAssessments;
use App\Models\Entities\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

final class SettleRestoredCriteriaUpdate implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $entityId) {}

    public function uniqueId(): string
    {
        return 'settle-restored-criteria-update:'.$this->entityId;
    }

    public function handle(RebindRestoredAssessments $rebind): void
    {
        $rebind->handle($this->entityId);
    }

    public function failed(?Throwable $exception): void
    {
        $entity = Entity::withTrashed()->find($this->entityId);

        if ($entity instanceof Entity) {
            app(HandleFailedRestoreRebind::class)->handle($entity);
        }
    }
}
