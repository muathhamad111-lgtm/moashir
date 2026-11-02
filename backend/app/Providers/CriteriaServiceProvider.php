<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CriteriaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('main', static fn (string $value): MainCriterion => MainCriterion::query()
            ->whereHas('criteriaVersion')
            ->whereKey((int) $value)
            ->firstOrFail());

        Route::bind('sub', static fn (string $value): SubCriterion => SubCriterion::query()
            ->whereHas('mainCriterion.criteriaVersion')
            ->whereKey((int) $value)
            ->firstOrFail());

        Route::bind('point', static fn (string $value): GuidingPoint => GuidingPoint::query()
            ->whereHas('subCriterion.mainCriterion.criteriaVersion')
            ->whereKey((int) $value)
            ->firstOrFail());
    }
}
