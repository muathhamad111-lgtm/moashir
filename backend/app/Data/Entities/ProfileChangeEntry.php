<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\EntityProfileField;
use App\Enums\Identity\ActorKind;
use App\Models\Entities\EntityProfileChange;
use Carbon\CarbonInterface;

final readonly class ProfileChangeEntry
{
    public function __construct(
        public int $id,
        public EntityProfileField $field,
        public mixed $oldValue,
        public mixed $newValue,
        public ActorKind $changedBy,
        public CarbonInterface $changedAt,
        public ?int $staffUserId = null,
    ) {}

    public static function from(EntityProfileChange $change, ?int $ownerId, ProfileChangePlaces $places): self
    {
        $actor = ActorKind::of($change->changed_by_user_id, $ownerId);

        return new self(
            $change->id,
            $change->field,
            $places->present($change->field, $change->old_value),
            $places->present($change->field, $change->new_value),
            $actor,
            $change->created_at,
            $actor === ActorKind::Staff ? $change->changed_by_user_id : null,
        );
    }
}
