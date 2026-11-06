<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Support\Audit\ActorRoles;

final readonly class AuditActorPresenter
{
    private const string SYSTEM = 'system';

    private const string USER = 'user';

    public function __construct(private AuditVisibility $visibility) {}

    /**
     * @return array{kind: string, id: int|null, name: string|null, roles: list<string>}
     */
    public function present(Audit $audit, User $viewer): array
    {
        if ($audit->user_id === null) {
            return ['kind' => self::SYSTEM, 'id' => null, 'name' => null, 'roles' => []];
        }

        if ($this->visibility->masksActorFor($viewer, $audit)) {
            return ['kind' => ActorRoles::SUPER_ADMIN_MARKER, 'id' => null, 'name' => null, 'roles' => [ActorRoles::SUPER_ADMIN_MARKER]];
        }

        $actor = $audit->user;

        return [
            'kind' => $actor instanceof User && $actor->isSuperAdmin() ? ActorRoles::SUPER_ADMIN_MARKER : self::USER,
            'id' => $audit->user_id,
            'name' => $actor instanceof User ? $actor->name : null,
            'roles' => $audit->actor_roles ?? [],
        ];
    }
}
