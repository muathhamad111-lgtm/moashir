<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Contact\ContactSettingsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Settings\SettingsChangeReason;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

final readonly class UpdateContactSettings
{
    private const string SECTION = 'contact';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private SettingsChangeReason $reasons,
    ) {}

    public function handle(User $actor, ContactSettingsUpdate $update): void
    {
        $this->reasons->record($actor, $update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(self::SECTION);

            return $this->store->put(SettingKey::ContactRetentionMonths, $update->retentionMonths, AuditEvent::ContactSettingsUpdated, $actor);
        });

        if ($changed) {
            $this->reasons->alertIfSuperAdmin($actor);
        }
    }
}
