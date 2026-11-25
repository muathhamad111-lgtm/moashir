<?php

declare(strict_types=1);

namespace App\Services\Entities\Timeline;

use App\Data\Entities\EntityTimelineEntry;
use App\Data\Entities\ProfileChangeEntry;
use App\Data\Entities\StaffProfileChangeEntry;
use App\Data\Identity\StaffActor;
use App\Enums\Entities\EntityTimelineKind;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Services\Entities\ProfileChangePlacesLoader;
use App\Services\Identity\StaffActorReader;
use Illuminate\Support\Collection;

/**
 * Turns one page of timeline rows into entries, loading each source (profile changes, profile-change requests,
 * e-mail changes and the staff who acted) once for the whole page.
 */
final readonly class EntityTimelineHydrator
{
    public function __construct(private ProfileChangePlacesLoader $places, private StaffActorReader $staffActors) {}

    /**
     * @param  list<TimelineRow>  $rows
     * @return list<EntityTimelineEntry>
     */
    public function hydrate(Entity $entity, array $rows): array
    {
        $sources = TimelineSources::load($rows);
        $actors = $this->staffActors->for($sources->staffUserIds());
        $changes = $this->fieldChangesOf($entity, $sources->fieldChanges, $actors);

        return array_values(array_filter(array_map(
            fn (TimelineRow $row): ?EntityTimelineEntry => $this->entryOf($entity, $row, $sources, $changes, $actors),
            $rows,
        )));
    }

    /**
     * @param  array<int, StaffProfileChangeEntry>  $changes
     * @param  array<int, StaffActor>  $actors
     */
    private function entryOf(Entity $entity, TimelineRow $row, TimelineSources $sources, array $changes, array $actors): ?EntityTimelineEntry
    {
        return match (true) {
            $row->kind->isLifecycle() => $this->lifecycleEntry($entity, $row),
            $row->kind === EntityTimelineKind::ProfileFieldChanged => isset($changes[$row->sourceId])
                ? new EntityTimelineEntry($row->kind, $row->sourceId, $row->occurredAt, change: $changes[$row->sourceId])
                : null,
            $row->kind === EntityTimelineKind::ProfileChangeRequested,
            $row->kind === EntityTimelineKind::ProfileChangeDecided => $this->requestEntry($row, $sources->requests->get($row->sourceId), $actors),
            default => $this->emailEntry($row, $sources->emails->get($row->sourceId), $actors),
        };
    }

    private function lifecycleEntry(Entity $entity, TimelineRow $row): EntityTimelineEntry
    {
        $column = $row->kind->reasonColumn();
        $reason = $column === null ? null : $entity->getAttribute($column);

        return new EntityTimelineEntry($row->kind, $row->sourceId, $row->occurredAt, reason: is_string($reason) ? $reason : null);
    }

    /**
     * @param  array<int, StaffActor>  $actors
     */
    private function requestEntry(TimelineRow $row, ?EntityProfileChangeRequest $request, array $actors): ?EntityTimelineEntry
    {
        if (! $request instanceof EntityProfileChangeRequest) {
            return null;
        }

        $decided = $row->kind === EntityTimelineKind::ProfileChangeDecided;

        return new EntityTimelineEntry(
            $row->kind,
            $row->sourceId,
            $row->occurredAt,
            actor: $decided ? $this->actorOf($request->reviewed_by_user_id, $actors) : null,
            status: $decided ? $request->status->value : null,
            reason: $decided ? $request->review_note : null,
            fields: array_values($request->changed_fields),
        );
    }

    /**
     * @param  array<int, StaffActor>  $actors
     */
    private function emailEntry(TimelineRow $row, ?EmailChangeRequest $change, array $actors): ?EntityTimelineEntry
    {
        if (! $change instanceof EmailChangeRequest) {
            return null;
        }

        $decided = $row->kind === EntityTimelineKind::EmailChangeDecided;

        return new EntityTimelineEntry(
            $row->kind,
            $row->sourceId,
            $row->occurredAt,
            actor: $decided ? $this->actorOf($change->decided_by_user_id, $actors) : null,
            status: $decided ? $change->status->value : null,
            reason: $decided ? $change->decision_reason : null,
            email: ['old' => $change->old_email, 'new' => $change->new_email],
        );
    }

    /**
     * @param  Collection<int, EntityProfileChange>  $fieldChanges
     * @param  array<int, StaffActor>  $actors
     * @return array<int, StaffProfileChangeEntry>
     */
    private function fieldChangesOf(Entity $entity, Collection $fieldChanges, array $actors): array
    {
        if ($fieldChanges->isEmpty()) {
            return [];
        }

        $ownerId = User::query()->where('entity_id', $entity->id)->value('id');
        $places = $this->places->for($fieldChanges);

        return $fieldChanges->map(function (EntityProfileChange $change) use ($ownerId, $places, $actors): StaffProfileChangeEntry {
            $entry = ProfileChangeEntry::from($change, is_numeric($ownerId) ? (int) $ownerId : null, $places);

            return new StaffProfileChangeEntry($entry, $entry->staffUserId === null ? null : $this->actorOf($entry->staffUserId, $actors));
        })->all();
    }

    /**
     * @param  array<int, StaffActor>  $actors
     */
    private function actorOf(?int $userId, array $actors): ?StaffActor
    {
        return $userId === null ? null : ($actors[$userId] ?? StaffActor::unknown());
    }
}
