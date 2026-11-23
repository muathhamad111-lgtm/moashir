<?php

declare(strict_types=1);

namespace App\Policies\Settings;

use App\Enums\Identity\PermissionKey;
use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;

/**
 * The staff non-disclosure agreement (versions, scope, register and reminders) is managed with its own
 * permission, separate from the public legal texts that content managers edit. A user may read again the
 * versions they accepted.
 */
final class NdaPolicy
{
    public function manage(User $user): bool
    {
        return $user->can(PermissionKey::NdaManage->value);
    }

    public function viewAccepted(User $user, TermsVersion $version): bool
    {
        return TermsAcceptance::query()->where('user_id', $user->id)->where('terms_version_id', $version->id)->exists();
    }
}
