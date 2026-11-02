<?php

declare(strict_types=1);

namespace App\Events\Entities;

use App\Models\Entities\Entity;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EntityRestored implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<string>  $unfrozenRequestNumbers
     */
    public function __construct(
        public Entity $entity,
        public array $unfrozenRequestNumbers,
        public int $actorId,
    ) {}
}
