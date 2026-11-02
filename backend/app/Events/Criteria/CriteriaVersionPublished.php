<?php

declare(strict_types=1);

namespace App\Events\Criteria;

use App\Models\Criteria\CriteriaVersion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CriteriaVersionPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CriteriaVersion $version, public ?CriteriaVersion $previous, public ?int $actorId) {}

    public function changedScale(): bool
    {
        return $this->previous instanceof CriteriaVersion && $this->previous->scale_kind !== $this->version->scale_kind;
    }
}
