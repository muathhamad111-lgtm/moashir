<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CycleListFilters;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final readonly class CycleListQuery
{
    public function __construct(private CycleRecordScope $scope) {}

    /**
     * @return Builder<AwardCycle>
     */
    public function filtered(User $viewer, CycleListFilters $filters): Builder
    {
        $query = AwardCycle::query();
        $this->scope->restrict($query, $viewer);

        if ($filters->status instanceof AwardCycleStatus) {
            $query->where('status', $filters->status);
        }

        if ($filters->search instanceof SearchTerm) {
            $pattern = $filters->search->containsPattern();
            $query->where(static fn (Builder $name): Builder => $name
                ->whereRaw("moashir_search_normalize(name->>'ar') like ?", [$pattern])
                ->orWhereRaw("moashir_search_normalize(name->>'en') like ?", [$pattern]));
        }

        return $query;
    }
}
