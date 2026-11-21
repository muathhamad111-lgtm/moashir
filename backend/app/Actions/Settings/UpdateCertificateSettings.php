<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Certificates\CertificateSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Settings\SettingsRejected;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCertificateSettings
{
    private const string SECTION = 'certificates';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private RequestContext $context,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(User $actor, CertificateSettingsUpdate $update): void
    {
        if ($update->isEmpty()) {
            return;
        }

        if ($actor->isSuperAdmin() && ($update->reason === null || $update->reason === '')) {
            throw SettingsRejected::reasonRequired();
        }

        $this->context->recordReason($update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(self::SECTION);
            $changed = false;

            foreach ($update->values() as $key => $value) {
                $changed = $this->store->put(SettingKey::from($key), $value, AuditEvent::CertificateSettingsUpdated, $actor) || $changed;
            }

            return $changed;
        });

        if ($changed && $actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }
}
