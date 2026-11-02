<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Casts\LocalizedTextCast;
use App\Enums\Identity\RoleKey;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property LocalizedText $label
 * @property LocalizedText|null $description
 * @property int $dashboard_priority
 * @property bool $is_system
 * @property bool $is_assignable
 * @property bool $is_cycle_scoped
 * @property-read Collection<int, Permission> $permissions
 */
final class Role extends SpatieRole
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'label' => LocalizedTextCast::class,
            'description' => LocalizedTextCast::class,
            'dashboard_priority' => 'integer',
            'is_system' => 'boolean',
            'is_assignable' => 'boolean',
            'is_cycle_scoped' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function assignable(Builder $query): void
    {
        $query->where('is_assignable', true);
    }

    public function key(): RoleKey
    {
        return RoleKey::from($this->name);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        $names = $this->permissions->map(static fn (Permission $permission): string => $permission->name)->all();
        sort($names);

        return $names;
    }
}
