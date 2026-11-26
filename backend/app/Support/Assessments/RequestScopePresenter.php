<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Identity\User;

final class RequestScopePresenter
{
    /**
     * @return array{all_paths: bool, paths: list<string>}
     */
    public static function of(User $user): array
    {
        $granted = $user->grantedRequestPaths();
        $paths = array_filter(AssessmentPath::reviewed(), static fn (AssessmentPath $path): bool => $granted === [] || in_array($path, $granted, true));

        return [
            'all_paths' => $granted === [],
            'paths' => array_values(array_map(static fn (AssessmentPath $path): string => $path->value, $paths)),
        ];
    }
}
