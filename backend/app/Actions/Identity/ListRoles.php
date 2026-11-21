<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\Role;
use Illuminate\Database\Eloquent\Collection;

final class ListRoles
{
    /**
     * @return Collection<int, Role>
     */
    public function handle(): Collection
    {
        return Role::query()->orderBy('dashboard_priority')->get();
    }
}
