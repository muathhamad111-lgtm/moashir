<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Identity\User;

final class RequestPathScope
{
    /**
     * @return list<AssessmentPath>
     */
    public function pathsFor(User $user): array
    {
        $granted = $user->isSuperAdmin() ? [] : $user->grantedRequestPaths();

        if ($granted === []) {
            return AssessmentPath::reviewed();
        }

        return array_values(array_filter(AssessmentPath::reviewed(), static fn (AssessmentPath $path): bool => in_array($path, $granted, true)));
    }

    public function isRestricted(User $user): bool
    {
        return ! $user->isSuperAdmin() && $user->grantedRequestPaths() !== [];
    }

    public function covers(User $user, AssessmentPath $path): bool
    {
        return ! $path->isReviewed() || in_array($path, $this->pathsFor($user), true);
    }

    /**
     * @return list<AssessmentPath>
     */
    public function excludedPathsFor(User $user): array
    {
        $followed = $this->pathsFor($user);

        return array_values(array_filter(AssessmentPath::reviewed(), static fn (AssessmentPath $path): bool => ! in_array($path, $followed, true)));
    }
}
