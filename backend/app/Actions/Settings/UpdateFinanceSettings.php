<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\FinanceSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\FinanceSettingField;
use App\Exceptions\Settings\SettingsRejected;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdateFinanceSettings
{
    private const string SECTION = 'finance';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private RequestContext $context,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(User $actor, FinanceSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        $this->recordReason($actor, $update->reason);

        if ($this->write($actor, $update) && $actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }

    private function recordReason(User $actor, ?string $reason): void
    {
        if ($actor->isSuperAdmin() && ($reason === null || $reason === '')) {
            throw SettingsRejected::reasonRequired();
        }

        $this->context->recordReason($reason);
    }

    private function write(User $actor, FinanceSettingsUpdate $update): bool
    {
        return DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(self::SECTION);
            $changed = false;

            foreach ($update->values() as $field => $value) {
                $key = FinanceSettingField::from($field)->settingKey();
                $changed = $this->store->put($key, $value, AuditEvent::FinanceSettingsUpdated, $actor) || $changed;
            }

            return $changed;
        });
    }
}
