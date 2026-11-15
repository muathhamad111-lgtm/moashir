<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\EntityRequestPath;
use App\Services\Paths\PathIdentityReader;

/**
 * Presents a path with its fixed label. Resources and data objects cannot take constructor dependencies, so the
 * shared reader is resolved from the container here, in one place.
 */
final class PathPresenter
{
    /**
     * @return array{path: string, path_label: array{ar: string, en: string}}
     */
    public static function of(AssessmentPath $path): array
    {
        return ['path' => $path->value, 'path_label' => self::reader()->nameOf($path)->toArray()];
    }

    /**
     * @return array{path: string, path_label: array{ar: string, en: string}|null}
     */
    public static function ofValue(string $value): array
    {
        $path = AssessmentPath::tryFrom($value);

        return ['path' => $value, 'path_label' => $path instanceof AssessmentPath ? self::reader()->nameOf($path)->toArray() : null];
    }

    /**
     * @return array{path: string|null, path_label: array{ar: string, en: string}|null}
     */
    public static function ofOptional(?AssessmentPath $path): array
    {
        return $path instanceof AssessmentPath ? self::of($path) : ['path' => null, 'path_label' => null];
    }

    /**
     * @return array{path: string, path_label: array{ar: string, en: string}}
     */
    public static function ofRequestPath(EntityRequestPath $path): array
    {
        return ['path' => $path->value, 'path_label' => self::reader()->labelOf($path)->toArray()];
    }

    private static function reader(): PathIdentityReader
    {
        return app(PathIdentityReader::class);
    }
}
