<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Services\Identity\DefaultPermissionMatrix;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncPermissionCatalog
{
    private const string GUARD = 'web';

    public function __construct(private DefaultPermissionMatrix $matrix, private PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $newPermissions = $this->syncPermissions();

            foreach (RoleKey::cases() as $roleKey) {
                $this->syncRole($roleKey, $newPermissions);
            }
        });

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function syncPermissions(): array
    {
        $created = [];

        foreach (PermissionKey::cases() as $index => $key) {
            $permission = Permission::query()->firstOrNew(['name' => $key->value, 'guard_name' => self::GUARD]);

            if (! $permission->exists) {
                $permission->forceFill($this->labels('permissions', $key->value));
                $created[] = $key->value;
            }

            $permission->forceFill([
                'module' => $key->module(),
                'is_system' => true,
                'is_confidential' => $key->isConfidential(),
                'is_grantable' => $key->isGrantable(),
                'locked_to_roles' => $key->isLocked() ? array_map(static fn (RoleKey $role): string => $role->value, $key->lockedToRoles()) : null,
                'sort_order' => $index + 1,
            ])->save();
        }

        return $created;
    }

    /**
     * @param  list<string>  $newPermissions
     */
    private function syncRole(RoleKey $roleKey, array $newPermissions): void
    {
        $role = Role::query()->firstOrNew(['name' => $roleKey->value, 'guard_name' => self::GUARD]);
        $isNew = ! $role->exists;

        if ($isNew) {
            $role->forceFill($this->labels('roles', $roleKey->value));
        }

        $role->forceFill([
            'dashboard_priority' => $roleKey->dashboardPriority(),
            'is_system' => true,
            'is_assignable' => $roleKey->isAssignable(),
            'is_cycle_scoped' => $roleKey->isCycleScoped(),
        ])->save();

        $defaults = array_map(static fn (PermissionKey $key): string => $key->value, $this->matrix->for($roleKey));

        if ($isNew || ! $roleKey->isAssignable()) {
            $role->syncPermissions($defaults);

            return;
        }

        $role->givePermissionTo(array_values(array_intersect($defaults, $newPermissions)));
    }

    /**
     * @return array{label: LocalizedText, description: LocalizedText}
     */
    private function labels(string $group, string $key): array
    {
        $arabic = $this->translatedEntry($group, $key, 'ar');
        $english = $this->translatedEntry($group, $key, 'en');

        return [
            'label' => new LocalizedText($arabic['label'], $english['label']),
            'description' => new LocalizedText($arabic['description'], $english['description']),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    private function translatedEntry(string $group, string $key, string $locale): array
    {
        $catalog = __($group, [], $locale);
        $entry = is_array($catalog) && is_array($catalog[$key] ?? null) ? $catalog[$key] : [];

        return [
            'label' => ScalarValue::string($entry['label'] ?? null),
            'description' => ScalarValue::string($entry['description'] ?? null),
        ];
    }
}
