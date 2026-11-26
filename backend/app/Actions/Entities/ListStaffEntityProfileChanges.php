<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\ProfileChangeEntry;
use App\Data\Entities\StaffProfileChangeEntry;
use App\Data\Identity\StaffActor;
use App\Enums\Entities\ProfileSection;
use App\Models\Entities\Entity;
use App\Services\Identity\StaffActorReader;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListStaffEntityProfileChanges
{
    public function __construct(private ListEntityProfileChanges $changes, private StaffActorReader $staffActors) {}

    /**
     * @return LengthAwarePaginator<int, StaffProfileChangeEntry>
     */
    public function handle(Entity $entity, int $perPage, ?ProfileSection $section = null): LengthAwarePaginator
    {
        $page = $this->changes->handle($entity, $perPage, $section);
        $authors = $this->staffActors->for($this->staffUserIdsOf($page->getCollection()->all()));

        return $page->through(static fn (ProfileChangeEntry $entry): StaffProfileChangeEntry => new StaffProfileChangeEntry(
            $entry,
            $entry->staffUserId === null ? null : ($authors[$entry->staffUserId] ?? StaffActor::unknown()),
        ));
    }

    /**
     * @param  array<int, ProfileChangeEntry>  $entries
     * @return list<int>
     */
    private function staffUserIdsOf(array $entries): array
    {
        return array_values(array_filter(array_map(static fn (ProfileChangeEntry $entry): ?int => $entry->staffUserId, $entries)));
    }
}
