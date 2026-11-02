<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\Permission;
use App\Support\ValueObjects\LocalizedText;

final class UpdatePermissionLabels
{
    public function handle(Permission $permission, LocalizedText $label, ?LocalizedText $description): Permission
    {
        $permission->forceFill(['label' => $label, 'description' => $description])->save();

        return $permission;
    }
}
