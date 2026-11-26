<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Nda\NdaReminderSource;
use App\Models\Identity\TermsVersion;
use App\Models\Nda\NdaReminder;
use App\Services\Audit\AuditRecorder;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Records a reminder round and audits it. A second scheduled round for the same version hits the partial unique
 * index and records nothing, which tells the caller not to send.
 */
final readonly class NdaReminderLog
{
    public function __construct(private NdaAudience $audience, private AuditRecorder $audit) {}

    public function open(TermsVersion $version, NdaReminderSource $source): ?NdaReminder
    {
        $inserted = DB::table('nda_reminders')->insertOrIgnoreReturning([
            'terms_version_id' => $version->id,
            'source' => $source->value,
            'recipients' => $this->audience->pending($version)->count(),
            'created_at' => Date::now(),
        ], ['id'])->all();
        $row = $inserted[0] ?? null;

        $reminder = is_object($row) && isset($row->id) ? NdaReminder::query()->find(ScalarValue::int($row->id)) : null;

        if ($reminder instanceof NdaReminder) {
            $this->audit->record(new AuditEntry(AuditEvent::NdaReminderSent, $reminder, [], [
                'terms_version_id' => $version->id,
                'source' => $source->value,
                'recipients' => $reminder->recipients,
            ]));
        }

        return $reminder;
    }
}
