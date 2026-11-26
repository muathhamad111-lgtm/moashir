<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\EntityTimelineEntry;
use App\Enums\Entities\EntityTimelineScope;
use App\Models\Entities\Entity;
use App\Services\Entities\Timeline\EntityTimelineHydrator;
use App\Services\Entities\Timeline\EntityTimelineQuery;
use App\Services\Entities\Timeline\TimelineRow;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListEntityTimeline
{
    public function __construct(private EntityTimelineQuery $query, private EntityTimelineHydrator $hydrator) {}

    /**
     * @return LengthAwarePaginator<int, EntityTimelineEntry>
     */
    public function handle(Entity $entity, int $perPage, EntityTimelineScope $scope): LengthAwarePaginator
    {
        $page = $this->query->for($entity, $scope)->paginate($perPage);
        $rows = array_values(array_map(TimelineRow::from(...), $page->items()));

        return new LengthAwarePaginator(
            $this->hydrator->hydrate($entity, $rows),
            $page->total(),
            $page->perPage(),
            $page->currentPage(),
        );
    }
}
