<?php

declare(strict_types=1);

namespace App\Events\Entities;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EntityPermanentlyDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $entityId, public string $reference) {}
}
