<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Casts\LocalizedTextCast;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\PermissionModule;
use App\Support\ValueObjects\LocalizedText;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property LocalizedText $label
 * @property LocalizedText|null $description
 * @property PermissionModule $module
 * @property bool $is_system
 * @property bool $is_confidential
 * @property bool $is_grantable
 * @property list<string>|null $locked_to_roles
 * @property int $sort_order
 */
final class Permission extends SpatiePermission implements Auditable
{
    use AuditableTrait;

    protected $guarded = ['id'];

    /**
     * @var list<string>
     */
    protected array $auditInclude = ['label', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'label' => LocalizedTextCast::class,
            'description' => LocalizedTextCast::class,
            'module' => PermissionModule::class,
            'is_system' => 'boolean',
            'is_confidential' => 'boolean',
            'is_grantable' => 'boolean',
            'locked_to_roles' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function key(): PermissionKey
    {
        return PermissionKey::from($this->name);
    }
}
