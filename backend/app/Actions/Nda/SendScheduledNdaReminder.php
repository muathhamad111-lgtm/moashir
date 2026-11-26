<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Enums\Nda\NdaReminderSource;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Jobs\Nda\SendNdaNotices;
use App\Models\Identity\TermsVersion;
use App\Models\Nda\NdaReminder;
use App\Services\Nda\NdaAcceptanceCheck;
use App\Services\Nda\NdaReminderLog;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * NDA-02 for the current version, once its reminder delay has passed. The partial unique index on the reminder
 * rows makes it run once per version, however often the scheduler fires or overlaps.
 */
final readonly class SendScheduledNdaReminder
{
    public function __construct(
        private NdaAcceptanceCheck $check,
        private NdaReminderLog $log,
        private SettingsStore $settings,
    ) {}

    public function handle(): bool
    {
        $version = $this->check->current();

        if (! $version instanceof TermsVersion || ! $this->isDue($version)) {
            return false;
        }

        $reminder = DB::transaction(fn (): ?NdaReminder => $this->log->open($version, NdaReminderSource::Auto));

        if (! $reminder instanceof NdaReminder) {
            return false;
        }

        SendNdaNotices::dispatch($version->id, NotificationCode::NdaAcceptanceReminder)->afterCommit();

        return true;
    }

    private function isDue(TermsVersion $version): bool
    {
        $days = ScalarValue::int($this->settings->get(SettingKey::NdaReminderDays));

        return $version->published_at instanceof Carbon
            && ! $version->published_at->copy()->addDays($days)->isFuture()
            && ! NdaReminder::query()->where('terms_version_id', $version->id)->where('source', NdaReminderSource::Auto)->exists();
    }
}
