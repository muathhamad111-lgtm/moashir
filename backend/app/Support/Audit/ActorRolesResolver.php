<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Identity\User;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

final class ActorRolesResolver implements Resolver
{
    /**
     * @return list<string>|null
     */
    public static function resolve(Auditable $auditable): ?array
    {
        $actor = Auth::user();

        return $actor instanceof User ? ActorRoles::of($actor) : null;
    }
}
