<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\EntityListFilters;
use App\Enums\Certificates\EntityLevelFilter;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityRecordScope;
use App\Support\Certificates\EntityLevelQuery;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListEntities
{
    public const array SORTABLE = [
        'name', '-name', 'created_at', '-created_at', 'founding_year', '-founding_year', 'governance_grade', '-governance_grade',
        'level', '-level',
    ];

    public function __construct(private EntityRecordScope $scope) {}

    /**
     * @return LengthAwarePaginator<int, Entity>
     */
    public function handle(User $actor, EntityListFilters $filters): LengthAwarePaginator
    {
        $query = $this->filteredQuery($actor, $filters)
            ->with(['owner', 'region', 'city', 'logo', EntityLevelQuery::RELATION])
            ->withExists(EntityLevelQuery::openAccreditedRequestFlag());
        $this->applySort($query, $filters->sort);

        return $query->orderBy('id')->paginate($filters->perPage);
    }

    /**
     * @return Builder<Entity>
     */
    public function filteredQuery(User $actor, EntityListFilters $filters): Builder
    {
        $query = Entity::query()->notErased();

        if ($filters->onlyDeleted) {
            $query->onlyTrashed();
        }

        $this->scope->restrict($query, $actor);

        if ($filters->search instanceof SearchTerm) {
            $query->matchingSearch($filters->search);
        }

        $exactFilters = [
            'status' => $filters->status,
            'region_id' => $filters->regionId,
            'city_id' => $filters->cityId,
            'entity_type' => $filters->entityType,
            'development_sector' => $filters->developmentSector,
            'org_size' => $filters->orgSize,
        ];

        foreach ($exactFilters as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        if ($filters->level instanceof EntityLevelFilter) {
            EntityLevelQuery::filter($query, $filters->level);
        }

        return $query;
    }

    /**
     * @param  Builder<Entity>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if ($column === 'level') {
            EntityLevelQuery::orderBy($query, $direction);

            return;
        }

        if ($column === 'name') {
            $query->orderByRaw("name->>'ar' ".$direction);

            return;
        }

        $query->orderBy($column, $direction);
    }
}
