<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Platform\DemoRecordKind;
use App\Enums\Platform\HousekeepingTask;
use App\Models\Files\StoredFile;
use App\Models\Platform\DemoRecord;
use App\Models\Platform\HousekeepingRun;
use App\Services\Audit\AuditRecorder;
use App\Services\Demo\AppendOnlyGuards;
use App\Services\Demo\DemoFiles;
use App\Services\Demo\DemoPurgeGuard;
use App\Services\Demo\DemoRecordPurger;
use App\Support\Demo\DemoPurgeTally;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class PurgeDemoData
{
    private const array ORDER = [DemoRecordKind::Entity, DemoRecordKind::InsightsAssessment, DemoRecordKind::User];

    public function __construct(
        private DemoPurgeGuard $guard,
        private AppendOnlyGuards $appendOnlyGuards,
        private DemoRecordPurger $records,
        private DemoFiles $files,
        private AuditRecorder $audit,
    ) {}

    public function handle(): DemoPurgeTally
    {
        $this->guard->assertMayPurge();
        $startedAt = Date::now();
        $tally = new DemoPurgeTally;

        foreach (self::ORDER as $kind) {
            foreach (DemoRecord::query()->where('kind', $kind)->orderBy('id')->get() as $record) {
                $this->files->removeContents($this->purgeInOwnTransaction($record, $tally));
            }
        }

        if (! $tally->isEmpty()) {
            $this->record($tally, $startedAt);
        }

        return $tally;
    }

    /**
     * @return list<StoredFile>
     */
    private function purgeInOwnTransaction(DemoRecord $record, DemoPurgeTally $tally): array
    {
        return DB::transaction(function () use ($record, $tally): array {
            $this->appendOnlyGuards->suspendForCurrentTransaction();
            $removedFiles = $this->records->purge($record, $tally);
            $this->appendOnlyGuards->restore();

            return $removedFiles;
        });
    }

    private function record(DemoPurgeTally $tally, CarbonInterface $startedAt): void
    {
        $run = HousekeepingRun::query()->create([
            'task' => HousekeepingTask::DemoPurge,
            'counts' => $tally->toArray(),
            'started_at' => $startedAt,
            'completed_at' => Date::now(),
        ]);

        $this->audit->record(new AuditEntry(AuditEvent::DemoPurged, $run, [], $tally->toArray()));
    }
}
