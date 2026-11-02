<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityNotice;
use App\Enums\Entities\EntityStatus;
use App\Enums\Identity\Locale;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

final class EntityNotifier
{
    private const int BULK_CHUNK = 500;

    public function toOwner(Entity $entity, EntityNotice $notice): void
    {
        $owner = User::query()->where('entity_id', $entity->id)->first();

        $owner?->notify(new EntityNotification($notice));
    }

    public function toRole(RoleKey $role, EntityNotice $notice, ?int $actorId): void
    {
        $this->sendToStaff(User::query()->role($role->value), $notice, $actorId);
    }

    public function toStaffUser(int $userId, EntityNotice $notice): void
    {
        $this->sendToStaff(User::query()->whereKey($userId), $notice, null);
    }

    /**
     * @param  list<int>  $userIds
     */
    public function toStaffUsers(array $userIds, EntityNotice $notice, ?int $actorId): void
    {
        if ($userIds !== []) {
            $this->sendToStaff(User::query()->whereKey($userIds), $notice, $actorId);
        }
    }

    /**
     * @param  list<int>  $regionIds
     */
    public function toActiveEntitiesIn(array $regionIds, EntityNotice $notice): int
    {
        $sent = 0;

        User::query()
            ->whereIn('entity_id', Entity::query()->select('id')->where('status', EntityStatus::Active)->whereIn('region_id', $regionIds))
            ->where('status', UserStatus::Active)
            ->chunkById(self::BULK_CHUNK, static function (Collection $owners) use ($notice, &$sent): void {
                Notification::send($owners, new EntityNotification($notice));
                $sent += $owners->count();
            });

        return $sent;
    }

    public function toPermissionHolders(PermissionKey $permission, EntityNotice $notice, ?int $actorId): void
    {
        $this->sendToStaff(User::query()->permission($permission->value), $notice, $actorId);
    }

    public function toAddress(EmailAddress $address, EntityNotice $notice, Locale $locale): void
    {
        Notification::route('mail', $address->value)->notify(new EntityNotification($notice->mailOnly())->locale($locale->value));
    }

    /**
     * @param  Builder<User>  $query
     */
    private function sendToStaff(Builder $query, EntityNotice $notice, ?int $actorId): void
    {
        $recipients = $query
            ->staff()
            ->withoutSuperAdmins()
            ->where('status', UserStatus::Active)
            ->when($actorId !== null, static fn (Builder $builder): Builder => $builder->whereKeyNot($actorId))
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new EntityNotification($notice));
        }
    }
}
