<?php

declare(strict_types=1);

namespace App\Events\Entities;

use App\Enums\Entities\EntityProfileField;
use App\Models\Entities\Entity;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EntityProfileUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<EntityProfileField>  $fields
     */
    public function __construct(public Entity $entity, public array $fields, public int $actorId) {}
}
