<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\ProfileChangePlaces;
use App\Enums\Entities\EntityProfileField;
use App\Models\Entities\City;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;
use Illuminate\Database\Eloquent\Builder;

final class ProfileChangePlacesLoader
{
    /**
     * @param  iterable<EntityProfileChange>  $changes
     */
    public function for(iterable $changes): ProfileChangePlaces
    {
        $regionIds = [];
        $cityIds = [];

        foreach ($changes as $change) {
            $ids = array_filter([$change->old_value, $change->new_value], is_int(...));

            if ($change->field === EntityProfileField::Region) {
                array_push($regionIds, ...$ids);
            } elseif ($change->field === EntityProfileField::City) {
                array_push($cityIds, ...$ids);
            }
        }

        return new ProfileChangePlaces($this->placesIn(Region::query(), $regionIds), $this->placesIn(City::query(), $cityIds));
    }

    /**
     * @param  Builder<Region>|Builder<City>  $query
     * @param  list<int>  $ids
     * @return array<int, array{id: int, code: string, name: array{ar: string, en: string}}>
     */
    private function placesIn(Builder $query, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $query->whereKey(array_unique($ids))->get()
            ->mapWithKeys(static fn (Region|City $place): array => [$place->id => EntityPresenter::place($place)])
            ->all();
    }
}
