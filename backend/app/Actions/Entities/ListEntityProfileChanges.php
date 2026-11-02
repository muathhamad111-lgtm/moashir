<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\ProfileChangeEntry;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Identity\User;
use App\Services\Entities\ProfileChangePlacesLoader;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListEntityProfileChanges
{
    public function __construct(private ProfileChangePlacesLoader $places) {}

    /**
     * @return LengthAwarePaginator<int, ProfileChangeEntry>
     */
    public function handle(Entity $entity, int $perPage): LengthAwarePaginator
    {
        $page = EntityProfileChange::query()
            ->where('entity_id', $entity->id)
            ->latest()
            ->latest('id')
            ->paginate($perPage);

        $ownerId = $this->ownerIdOf($entity);
        $places = $this->places->for($page->getCollection());

        return $page->through(static fn (EntityProfileChange $change): ProfileChangeEntry => ProfileChangeEntry::from($change, $ownerId, $places));
    }

    private function ownerIdOf(Entity $entity): ?int
    {
        $ownerId = User::query()->where('entity_id', $entity->id)->value('id');

        return is_numeric($ownerId) ? (int) $ownerId : null;
    }
}
