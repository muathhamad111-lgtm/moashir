<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Audit\AuditEntry;
use App\Data\Cms\SiteSettingsChanges;
use App\Enums\Audit\AuditEvent;
use App\Events\Cms\SiteSettingsUpdated;
use App\Models\Cms\SiteSettings;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Platform\TransactionLock;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSiteSettings
{
    public function __construct(private TransactionLock $lock, private AuditRecorder $audit) {}

    public function handle(User $actor, SiteSettingsChanges $changes): void
    {
        if ($changes->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($actor, $changes): void {
            $this->lock->lock('cms:site-settings');
            $settings = SiteSettings::current();
            $fields = array_keys($changes->attributes);
            $before = $this->valuesOf($settings, $fields);
            $settings->fill($changes->attributes);

            if (! $settings->isDirty()) {
                return;
            }

            $settings->forceFill(['updated_by_user_id' => $actor->id])->save();
            $this->audit->record(new AuditEntry(AuditEvent::CmsSiteSettingsUpdated, $settings, $before, $this->valuesOf($settings, $fields)));

            SiteSettingsUpdated::dispatch($actor->id);
        });
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function valuesOf(SiteSettings $settings, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $settings->getAttribute($field);
            $values[$field] = is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : $value;
        }

        return $values;
    }
}
