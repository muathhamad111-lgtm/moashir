<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Platform\HousekeepingTask;
use App\Models\Contact\ContactMessage;
use App\Models\Platform\HousekeepingRun;
use App\Services\Audit\AuditRecorder;
use App\Services\Contact\ContactMessageDeletion;
use App\Services\Contact\ContactRetention;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class PurgeClosedContactMessages
{
    private const int CHUNK = 200;

    public function __construct(
        private ContactRetention $retention,
        private ContactMessageDeletion $deletion,
        private AuditRecorder $audit,
    ) {}

    public function handle(): HousekeepingRun
    {
        $startedAt = Date::now();
        $months = $this->retention->months();
        $counts = ['purged_messages' => 0, 'purged_entries' => 0, 'retention_months' => $months];

        ContactMessage::query()
            ->select('id')
            ->where('status', ContactMessageStatus::Closed)
            ->where('closed_at', '<', $this->retention->closedBefore())
            ->chunkById(self::CHUNK, function (Collection $chunk) use (&$counts): void {
                $deleted = DB::transaction(fn (): array => $this->deletion->delete(array_values(array_map(ScalarValue::int(...), $chunk->modelKeys()))));
                $counts['purged_messages'] += $deleted['messages'];
                $counts['purged_entries'] += $deleted['entries'];
            });

        $run = HousekeepingRun::query()->create([
            'task' => HousekeepingTask::ContactMessagePurge,
            'counts' => $counts,
            'started_at' => $startedAt,
            'completed_at' => Date::now(),
        ]);

        if ($counts['purged_messages'] > 0) {
            $this->audit->record(new AuditEntry(AuditEvent::ContactMessagesPurged, $run, [], $counts));
        }

        return $run;
    }
}
