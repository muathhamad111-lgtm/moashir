<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Models\Settings\Setting;
use App\Services\Audit\AuditRecorder;

final readonly class SettingsStore
{
    public function __construct(private AuditRecorder $audit) {}

    public function get(SettingKey $key): mixed
    {
        $setting = Setting::query()->where('key', $key->value)->first();

        return $setting instanceof Setting ? $setting->value : $key->defaultValue();
    }

    /**
     * @param  list<SettingKey>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $stored = $this->stored($keys);
        $values = [];

        foreach ($keys as $key) {
            $values[$key->value] = array_key_exists($key->value, $stored) ? $stored[$key->value] : $key->defaultValue();
        }

        return $values;
    }

    /**
     * Only the keys that were saved, without defaults.
     *
     * @param  list<SettingKey>  $keys
     * @return array<string, mixed>
     */
    public function stored(array $keys): array
    {
        $values = [];
        $settings = Setting::query()->whereIn('key', array_map(static fn (SettingKey $key): string => $key->value, $keys))->get();

        foreach ($settings as $setting) {
            $values[$setting->key] = $setting->value;
        }

        return $values;
    }

    public function put(SettingKey $key, mixed $value, AuditEvent $event, ?User $actor): bool
    {
        $setting = Setting::query()->firstOrNew(['key' => $key->value], ['group' => $key->group(), 'type' => $key->type()]);
        $before = $setting->exists ? $setting->value : $key->defaultValue();

        if ($setting->exists && $before === $value) {
            return false;
        }

        $setting->forceFill(['value' => $value, 'updated_by_user_id' => $actor?->id])->save();

        if ($before !== $value) {
            $this->audit->record(new AuditEntry($event, $setting, [$key->value => $before], [$key->value => $value]));
        }

        return $before !== $value;
    }
}
