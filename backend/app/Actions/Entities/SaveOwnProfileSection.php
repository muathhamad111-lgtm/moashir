<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityProfileChangeSet;
use App\Data\Entities\ProfileSectionSaved;
use App\Data\Entities\ProfileSectionUpdate;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntityProfileUpdated;
use App\Exceptions\Entities\ProfileSectionRejected;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityProfileWriter;
use App\Support\Entities\ProfileFieldValues;
use App\Support\Entities\ProfileSectionRevision;
use Illuminate\Support\Facades\DB;

final readonly class SaveOwnProfileSection
{
    public function __construct(private EntityProfileWriter $writer, private AuditRecorder $audit) {}

    public function handle(User $owner, ProfileSectionUpdate $update): ProfileSectionSaved
    {
        return DB::transaction(function () use ($owner, $update): ProfileSectionSaved {
            $entity = $this->lockedEntityOf($owner);
            $before = $this->assertCurrent($entity, $update);

            $changes = $this->writer->write($entity, $update->write, $owner);
            $this->announce($entity, $update, $changes, $owner);

            $after = ProfileFieldValues::of($entity->refresh())->editableValuesOf($update->section);

            return new ProfileSectionSaved($entity, $update->section, $this->changedFields($before, $after));
        });
    }

    /**
     * @param  array<string, string|int|bool|null>  $before
     * @param  array<string, string|int|bool|null>  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        return array_keys(array_filter(
            $after,
            static fn (string|int|bool|null $value, string $field): bool => $value !== ($before[$field] ?? null),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    private function lockedEntityOf(User $owner): Entity
    {
        $entity = Entity::query()->whereKey($owner->entity_id)->lockForUpdate()->firstOrFail();

        if (! $entity->isActive()) {
            throw ProfileSectionRejected::readOnly();
        }

        return $entity->setRelation('owner', User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail());
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    private function assertCurrent(Entity $entity, ProfileSectionUpdate $update): array
    {
        $values = ProfileFieldValues::of($entity);
        $current = ProfileSectionRevision::of($values, $update->section);

        if (! hash_equals($current, $update->baseRevision)) {
            throw ProfileSectionRejected::stale($update->section, $current);
        }

        return $values->editableValuesOf($update->section);
    }

    private function announce(Entity $entity, ProfileSectionUpdate $update, EntityProfileChangeSet $changes, User $owner): void
    {
        if ($changes->isEmpty()) {
            return;
        }

        $audited = $changes->withoutPersonalData();

        $this->audit->record(new AuditEntry(AuditEvent::EntityProfileUpdated, $entity, $audited->before, [
            ...$audited->after,
            'section' => $update->section->value,
            'changed_fields' => array_keys($changes->after),
        ]));

        if ($changes->touchesEntityRecord()) {
            EntityProfileUpdated::dispatch($entity, $changes->fields(), $owner->id);
        }
    }
}
