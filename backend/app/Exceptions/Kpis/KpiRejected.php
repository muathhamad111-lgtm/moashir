<?php

declare(strict_types=1);

namespace App\Exceptions\Kpis;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class KpiRejected extends ApiException
{
    public static function slugTaken(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'slug.taken', [
            'slug' => [__('errors.slug.taken')],
        ]));
    }

    public static function featuredLimitReached(int $limit): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'kpi.featured_limit_reached', [
            'show_on_homepage' => [__('errors.kpi.featured_limit_reached', ['limit' => (string) $limit])],
        ], [], ['limit' => $limit]));
    }

    public static function measurementExists(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'kpi.measurement_exists', [
            'measured_on' => [__('errors.kpi.measurement_exists')],
        ]));
    }

    public static function categoryInUse(int $kpiCount): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'category.in_use', [], [], ['kpi_count' => $kpiCount]));
    }

    public static function typeInUse(int $kpiCount): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'type.in_use', [], [], ['kpi_count' => $kpiCount]));
    }
}
