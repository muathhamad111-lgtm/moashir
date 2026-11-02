<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Platform\HousekeepingTask;
use App\Models\Files\StoredFile;
use App\Models\Platform\HousekeepingRun;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileDisk;
use App\Support\Files\OrphanPurgeTally;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class PurgeOrphanFiles
{
    private const int CHUNK = 200;

    public function __construct(private StoredFileDisk $disk, private AuditRecorder $audit, private Repository $config) {}

    public function handle(): HousekeepingRun
    {
        $startedAt = Date::now();
        $graceHours = max(1, $this->config->integer('moashir.files.orphan_grace_hours'));
        $tally = new OrphanPurgeTally($graceHours);

        StoredFile::withTrashed()
            ->orphaned()
            ->where('created_at', '<', $startedAt->copy()->subHours($graceHours))
            ->lazyById(self::CHUNK)
            ->each(function (StoredFile $file) use ($tally): void {
                if ($this->purge($file)) {
                    $tally->add($file);
                }
            });

        return $this->record($tally, $startedAt);
    }

    private function purge(StoredFile $file): bool
    {
        $deleted = StoredFile::withTrashed()->whereKey($file->id)->whereNull('attachable_type')->forceDelete();

        if ($deleted === 0) {
            return false;
        }

        $this->disk->delete($file);

        return true;
    }

    private function record(OrphanPurgeTally $tally, CarbonInterface $startedAt): HousekeepingRun
    {
        $run = HousekeepingRun::query()->create([
            'task' => HousekeepingTask::OrphanFilePurge,
            'counts' => $tally->toArray(),
            'started_at' => $startedAt,
            'completed_at' => Date::now(),
        ]);

        if (! $tally->isEmpty()) {
            $this->audit->record(new AuditEntry(AuditEvent::OrphanFilesPurged, $run, [], $tally->toArray()));
        }

        return $run;
    }
}
