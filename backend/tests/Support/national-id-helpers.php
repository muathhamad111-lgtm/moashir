<?php

declare(strict_types=1);

use App\Data\Identity\NationalIdEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\IdentityChangeSource;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Identity\User;
use App\Services\Identity\NationalIdAssigner;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\NationalId;

function fictionalNationalId(int $serial, int $leading = 1): string
{
    return $leading.str_pad((string) $serial, NationalId::LENGTH - 1, '0', STR_PAD_LEFT);
}

function requireNationalIdFor(RoleKey ...$roles): void
{
    $store = app(SettingsStore::class);
    $store->put(SettingKey::NationalIdEnabled, true, AuditEvent::AuthSettingsUpdated, null);
    $store->put(SettingKey::NationalIdRoles, array_map(static fn (RoleKey $role): string => $role->value, $roles), AuditEvent::AuthSettingsUpdated, null);
}

function giveNationalId(User $user, int $serial): User
{
    app(NationalIdAssigner::class)->assignFirst($user, new NationalId(fictionalNationalId($serial)), new NationalIdEntry($user, IdentityChangeSource::AdminEntry));

    return $user->refresh();
}
