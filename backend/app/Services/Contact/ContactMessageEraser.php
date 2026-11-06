<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Contracts\Entities\EntityErasureStep;
use App\Models\Contact\ContactMessage;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class ContactMessageEraser implements EntityErasureStep
{
    public function __construct(private ContactMessageDeletion $deletion) {}

    public function erase(Entity $entity): void
    {
        $ownerIds = User::query()->withTrashed()->where('entity_id', $entity->id)->pluck('id');

        $ids = ContactMessage::query()
            ->where(static fn (Builder $linked): Builder => $linked->where('entity_id', $entity->id)->orWhereIn('user_id', $ownerIds))
            ->pluck('id')
            ->all();

        $this->deletion->delete(array_values(array_map(ScalarValue::int(...), $ids)));
    }
}
