<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Enums\Entities\ProfileChangeStatus;
use App\Exceptions\Entities\ProfileChangeRejected;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\User;
use App\Services\Entities\ProfileChanges\PendingProfileChanges;
use Illuminate\Support\Facades\DB;

final readonly class CancelOwnProfileChange
{
    public function __construct(private PendingProfileChanges $pending) {}

    public function handle(User $owner): Entity
    {
        return DB::transaction(function () use ($owner): Entity {
            $entity = Entity::query()->whereKey($owner->entity_id)->lockForUpdate()->firstOrFail();
            $request = EntityProfileChangeRequest::query()
                ->where('entity_id', $entity->id)
                ->where('status', ProfileChangeStatus::Pending)
                ->lockForUpdate()
                ->first();

            if (! $request instanceof EntityProfileChangeRequest) {
                throw ProfileChangeRejected::missing();
            }

            $this->pending->cancel($request->setRelation('entity', $entity), $owner);

            return $entity;
        });
    }
}
