<?php

declare(strict_types=1);

namespace App\Actions\Nda;

use App\Data\Nda\NdaOverview;
use App\Enums\Nda\NdaReminderSource;
use App\Enums\Settings\SettingKey;
use App\Models\Nda\NdaReminder;
use App\Services\Nda\NdaAcceptanceCheck;
use App\Services\Nda\NdaDraftFinder;
use App\Services\Nda\NdaScope;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use Carbon\CarbonInterface;

/**
 * What the agreement settings page shows on opening: the current version and the draft, the roles in scope, the
 * reminder delay and when a manual reminder may next be sent.
 */
final readonly class GetNdaOverview
{
    public function __construct(
        private NdaAcceptanceCheck $check,
        private NdaDraftFinder $drafts,
        private NdaScope $scope,
        private SettingsStore $settings,
    ) {}

    public function handle(): NdaOverview
    {
        return new NdaOverview(
            $this->check->current(),
            $this->drafts->find(),
            $this->scope->roleValues(),
            ScalarValue::int($this->settings->get(SettingKey::NdaReminderDays)),
            $this->nextManualReminderAt(),
        );
    }

    private function nextManualReminderAt(): ?CarbonInterface
    {
        $last = NdaReminder::query()->where('source', NdaReminderSource::Manual)->latest('created_at')->first();
        $next = $last?->created_at->copy()->addHours(SendNdaReminders::MANUAL_INTERVAL_HOURS);

        return $next !== null && $next->isFuture() ? $next : null;
    }
}
