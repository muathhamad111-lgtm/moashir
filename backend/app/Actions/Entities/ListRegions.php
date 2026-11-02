<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Models\Entities\Region;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ListRegions
{
    /**
     * @return Collection<int, Region>
     */
    public function handle(): Collection
    {
        return Region::query()
            ->active()
            ->ordered()
            ->with(['cities' => static fn (HasMany $cities): HasMany => $cities->where('is_active', true)])
            ->get();
    }
}
