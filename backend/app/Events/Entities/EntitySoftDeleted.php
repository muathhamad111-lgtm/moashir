<?php

declare(strict_types=1);

namespace App\Events\Entities;

use App\Models\Entities\Entity;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EntitySoftDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $frozenRequestNumbers
     */
    public function __construct(public Entity $entity, public array $frozenRequestNumbers, public int $actorId) {}
}
