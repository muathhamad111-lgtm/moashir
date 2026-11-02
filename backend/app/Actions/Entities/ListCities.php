<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\CityListFilters;
use App\Models\Entities\City;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListCities
{
    /**
     * @return LengthAwarePaginator<int, City>
     */
    public function handle(CityListFilters $filters): LengthAwarePaginator
    {
        $query = City::query()->with('region');

        if ($filters->regionId !== null) {
            $query->where('region_id', $filters->regionId);
        }

        if ($filters->active !== null) {
            $query->where('is_active', $filters->active);
        }

        if ($filters->search instanceof SearchTerm) {
            $pattern = $filters->search->containsPattern();
            $normalize = SearchTextNormalizer::DATABASE_FUNCTION;
            $query->where(static fn (Builder $inner): Builder => $inner
                ->whereRaw($normalize."(name->>'ar') like ?", [$pattern])
                ->orWhereRaw($normalize."(name->>'en') like ?", [$pattern]));
        }

        return $query->orderBy('region_id')->orderBy('sort_order')->orderBy('id')->paginate($filters->perPage);
    }
}
