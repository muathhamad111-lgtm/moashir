<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Entities\ProfileSectionUpdate;
use App\Exceptions\Entities\ProfileSectionRejected;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\OwnMarketingConsentWriter;
use App\Services\Entities\ProfileChanges\PendingProfileChanges;
use App\Services\Entities\ProfileChanges\ProfileChangeValues;
use App\Support\Entities\ProfileFieldValues;
use App\Support\Entities\ProfileSectionRevision;
use Illuminate\Support\Facades\DB;

/**
 * Puts the entity's edit of one profile section into its pending change request; nothing reaches the entity's
 * approved profile until staff approve it. Marketing consent is the one exception and applies at once.
 */
final readonly class ProposeOwnProfileSection
{
    public function __construct(
        private PendingProfileChanges $pending,
        private ProfileChangeValues $values,
        private OwnMarketingConsentWriter $consent,
    ) {}

    public function handle(User $owner, ProfileSectionUpdate $update): Entity
    {
        return DB::transaction(function () use ($owner, $update): Entity {
            $entity = $this->lockedEntityOf($owner);
            $this->assertCurrent($entity, $update);
            $this->consent->write($entity, $update->write->marketingConsent, $owner);

            $request = $this->pending->openFor($entity, $owner);
            $proposed = $this->values->proposedBy($entity, $update->write);
            $request->proposeFields($update->section->historyFields(), $proposed, $this->values->approvedOf($entity, array_keys($proposed)));
            $this->pending->store($request, $owner);

            return $entity->refresh();
        });
    }

    private function lockedEntityOf(User $owner): Entity
    {
        $entity = Entity::query()->whereKey($owner->entity_id)->lockForUpdate()->firstOrFail();

        if (! $entity->isActive()) {
            throw ProfileSectionRejected::readOnly();
        }

        return $entity->setRelation('owner', User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail());
    }

    private function assertCurrent(Entity $entity, ProfileSectionUpdate $update): void
    {
        $current = ProfileSectionRevision::of(ProfileFieldValues::of($entity), $update->section);

        if (! hash_equals($current, $update->baseRevision)) {
            throw ProfileSectionRejected::stale($update->section, $current);
        }
    }
}
