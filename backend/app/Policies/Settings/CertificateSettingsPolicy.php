<?php

declare(strict_types=1);

namespace App\Policies\Settings;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;

final class CertificateSettingsPolicy
{
    public function manage(User $user): bool
    {
        return $user->can(PermissionKey::SettingsCertificatesManage->value);
    }
}
