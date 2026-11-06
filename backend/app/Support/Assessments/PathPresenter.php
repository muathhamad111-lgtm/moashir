<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Enums\Assessments\AssessmentPath;

final class PathPresenter
{
    /**
     * @return array{path: string, path_label: array{ar: string, en: string}}
     */
    public static function of(AssessmentPath $path): array
    {
        return ['path' => $path->value, 'path_label' => $path->label()->toArray()];
    }

    /**
     * @return array{path: string, path_label: array{ar: string, en: string}|null}
     */
    public static function ofValue(string $value): array
    {
        return ['path' => $value, 'path_label' => AssessmentPath::tryFrom($value)?->label()->toArray()];
    }
}
