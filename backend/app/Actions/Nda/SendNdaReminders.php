<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Enums\Nda\NdaReminderSource;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Nda\NdaRejected;
use App\Jobs\Nda\SendNdaNotices;
use App\Models\Nda\NdaReminder;
use App\Services\Nda\NdaAcceptanceCheck;
use App\Services\Nda\NdaReminderLog;
use App\Services\Platform\TransactionLock;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * An administrator reminds everyone who has not accepted the current version yet, at most once every 24 hours.
 */
final readonly class SendNdaReminders
{
    public const int MANUAL_INTERVAL_HOURS = 24;

    public function __construct(
        private NdaAcceptanceCheck $check,
        private NdaReminderLog $log,
        private TransactionLock $lock,
    ) {}

    public function handle(): NdaReminder
    {
        $version = $this->check->current() ?? throw NdaRejected::noCurrentVersion();

        $reminder = DB::transaction(function () use ($version): NdaReminder {
            $this->lock->lock('nda:reminders');
            $this->assertIntervalPassed();

            return $this->log->open($version, NdaReminderSource::Manual) ?? throw NdaRejected::reminderTooSoon(self::MANUAL_INTERVAL_HOURS * 3600);
        });

        SendNdaNotices::dispatch($version->id, NotificationCode::NdaAcceptanceReminder)->afterCommit();

        return $reminder;
    }

    private function assertIntervalPassed(): void
    {
        $last = NdaReminder::query()->where('source', NdaReminderSource::Manual)->latest('created_at')->first();

        if (! $last instanceof NdaReminder) {
            return;
        }

        $nextAllowed = $last->created_at->copy()->addHours(self::MANUAL_INTERVAL_HOURS);

        if ($nextAllowed->isFuture()) {
            throw NdaRejected::reminderTooSoon((int) Date::now()->diffInSeconds($nextAllowed));
        }
    }
}
