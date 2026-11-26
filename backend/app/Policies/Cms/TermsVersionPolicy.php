<?php

declare(strict_types=1);

namespace App\Policies\Cms;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;

final class TermsVersionPolicy
{
    /**
     * Content managers manage the public legal texts only; a non-disclosure agreement version is refused here
     * even by its id, since it has its own permission (`nda.manage`).
     */
    public function manage(User $user, ?TermsVersion $version = null): bool
    {
        if ($version instanceof TermsVersion && ! $version->termsKind()->isLegal()) {
            return false;
        }

        return $user->can(PermissionKey::ContentHomeManage->value);
    }
}
