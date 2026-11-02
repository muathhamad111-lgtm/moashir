<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Identity\User;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

final class ActorEntityResolver implements Resolver
{
    public static function resolve(Auditable $auditable): ?int
    {
        $actor = Auth::user();

        return $actor instanceof User ? $actor->entity_id : null;
    }
}
