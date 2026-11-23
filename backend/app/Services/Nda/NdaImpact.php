<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Enums\Identity\RoleKey;
use App\Support\Nda\NdaRoleNames;
use Illuminate\Database\Eloquent\Builder;

/**
 * How many active staff a new version will ask to accept, per role in scope and in total (a user holding two
 * roles counts once in the total). Every published version needs everyone's acceptance again.
 */
final readonly class NdaImpact
{
    public function __construct(private NdaScope $scope, private NdaAudience $audience) {}

    /**
     * @return array{total: int, roles: list<array{role: string, count: int}>}
     */
    public function ofNewVersion(): array
    {
        $roles = array_map(fn (RoleKey $role): array => [
            'role' => $role->value,
            'count' => $this->audience->inScope()->whereHas('roles', static fn (Builder $query): Builder => $query->where('name', $role->value))->count(),
        ], $this->scope->roles());

        $superAdmins = ['role' => NdaRoleNames::SUPER_ADMIN, 'count' => $this->audience->inScope()->superAdmins()->count()];

        return ['total' => $this->audience->inScope()->count(), 'roles' => [$superAdmins, ...$roles]];
    }
}
