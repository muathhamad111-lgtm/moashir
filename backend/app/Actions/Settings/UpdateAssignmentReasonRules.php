<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Assessments\AssignmentReasonRulesUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Settings\SettingsChangeReason;
use App\Services\Settings\SettingsLock;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAssignmentReasonRules
{
    private const string SECTION = 'assignment_reasons';

    public function __construct(
        private SettingsStore $store,
        private SettingsLock $lock,
        private SettingsChangeReason $reasons,
    ) {}

    public function handle(User $actor, AssignmentReasonRulesUpdate $update): void
    {
        $this->reasons->record($actor, $update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->lock(self::SECTION);
            $assign = $this->store->put(SettingKey::AssignReasonRequired, $update->rules->assignRequired, AuditEvent::AssessmentSettingsUpdated, $actor);
            $reassign = $this->store->put(SettingKey::ReassignReasonRequired, $update->rules->reassignRequired, AuditEvent::AssessmentSettingsUpdated, $actor);

            return $assign || $reassign;
        });

        if ($changed) {
            $this->reasons->alertIfSuperAdmin($actor);
        }
    }
}
