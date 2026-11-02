<?php

declare(strict_types=1);

namespace App\Events\Entities;

use App\Models\Entities\Entity;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EntityReactivated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Entity $entity, public int $actorId) {}
}
