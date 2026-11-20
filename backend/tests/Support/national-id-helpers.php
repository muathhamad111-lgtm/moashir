<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Identity\NationalIdAssigner;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\NationalId;

function fictionalNationalId(int $serial, int $leading = 1): string
{
    $body = $leading.str_pad((string) $serial, 8, '0', STR_PAD_LEFT);

    foreach (range(0, 9) as $check) {
        if (NationalId::isValid($body.$check)) {
            return $body.$check;
        }
    }

    throw new LogicException('No check digit fits.');
}

function requireNationalIdFor(RoleKey ...$roles): void
{
    $store = app(SettingsStore::class);
    $store->put(SettingKey::NationalIdEnabled, true, AuditEvent::AuthSettingsUpdated, null);
    $store->put(SettingKey::NationalIdRoles, array_map(static fn (RoleKey $role): string => $role->value, $roles), AuditEvent::AuthSettingsUpdated, null);
}

function giveNationalId(User $user, int $serial): User
{
    app(NationalIdAssigner::class)->assignFirst($user, new NationalId(fictionalNationalId($serial)));

    return $user->refresh();
}
