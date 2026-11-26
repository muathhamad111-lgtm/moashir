<?php

declare(strict_types=1);

namespace App\Policies\Settings;

use App\Enums\Identity\PermissionKey;
use App\Enums\Settings\FinanceSettingField;
use App\Models\Identity\User;

final class FinanceSettingsPolicy
{
    public function manage(User $user): bool
    {
        return array_any(FinanceSettingField::cases(), fn (FinanceSettingField $field): bool => $this->editField($user, $field));
    }

    public function editField(User $user, FinanceSettingField $field): bool
    {
        $permission = $field->permission();

        return $permission instanceof PermissionKey && $user->can($permission->value);
    }
}
