<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\AssessmentSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Settings\SettingsRejected;
use App\Jobs\Assessments\RecomputeAccreditedDueDatesJob;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAssessmentSettings
{
    private const string SECTION = 'assessment';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private RequestContext $context,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(User $actor, AssessmentSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        if ($actor->isSuperAdmin() && ($update->reason === null || $update->reason === '')) {
            throw SettingsRejected::reasonRequired();
        }

        $this->context->recordReason($update->reason);
        $changed = $this->write($actor, $update);

        if (in_array(SettingKey::AccreditedReviewSlaDays->value, $changed, true)) {
            RecomputeAccreditedDueDatesJob::dispatch();
        }

        if ($changed !== [] && $actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }

    /**
     * @return list<string>
     */
    private function write(User $actor, AssessmentSettingsUpdate $update): array
    {
        return DB::transaction(function () use ($actor, $update): array {
            $this->lock->lock(self::SECTION);
            $changed = [];

            foreach ($update->values() as $key => $value) {
                if ($this->store->put(SettingKey::from($key), $value, AuditEvent::AssessmentSettingsUpdated, $actor)) {
                    $changed[] = $key;
                }
            }

            return $changed;
        });
    }
}
