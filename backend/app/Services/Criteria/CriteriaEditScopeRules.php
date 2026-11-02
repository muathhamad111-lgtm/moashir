<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Enums\Criteria\CriteriaEditScope;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Identity\User;

final class CriteriaEditScopeRules
{
    public function scopeFor(bool $replacesPublishedVersion, ?CriteriaEditScope $requested): ?CriteriaEditScope
    {
        if (! $replacesPublishedVersion) {
            return null;
        }

        return $requested ?? throw CriteriaVersionRejected::scopeRequired();
    }

    public function assertReason(User $actor, ?string $reason): void
    {
        if ($actor->isSuperAdmin() && ($reason === null || trim($reason) === '')) {
            throw CriteriaVersionRejected::reasonRequired();
        }
    }
}
