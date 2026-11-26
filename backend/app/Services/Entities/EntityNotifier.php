<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\EntityNotice;
use App\Data\Entities\StaffAudience;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\EntityStatus;
use App\Enums\Identity\Locale;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Support\ValueObjects\EmailAddress;
use Closure;
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

    /**
     * @param  Collection<int, Entity>  $entities
     * @param  Closure(Entity): EntityNotice  $noticeFor
     */
    public function toEachOwner(Collection $entities, Closure $noticeFor): void
    {
        $owners = User::query()->whereIn('entity_id', $entities->modelKeys())->where('status', UserStatus::Active)->get()->keyBy('entity_id');

        foreach ($entities as $entity) {
            $owner = $owners->get($entity->id);

            if ($owner instanceof User) {
                $owner->notify(new EntityNotification($noticeFor($entity)));
            }
        }
    }

    public function toRole(RoleKey $role, EntityNotice $notice, ?int $actorId): void
    {
        $this->sendToStaff($this->followingNoticePath(User::query()->role($role->value), $notice), $notice, $actorId);
    }

    public function toAudience(StaffAudience $audience, EntityNotice $notice): void
    {
        $query = User::query()->role($audience->roleValues());

        foreach ($audience->holding as $permission) {
            $query->permission($permission->value);
        }

        foreach ($audience->lacking as $permission) {
            $query->withoutPermission($permission->value);
        }

        $this->sendToStaff($this->followingNoticePath($query, $notice), $notice, null);
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
        $this->sendToStaff($this->followingNoticePath(User::query()->permission($permission->value), $notice), $notice, $actorId);
    }

    public function toAddress(EmailAddress $address, EntityNotice $notice, Locale $locale): void
    {
        Notification::route('mail', $address->value)->notify(new EntityNotification($notice->mailOnly())->locale($locale->value));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function followingNoticePath(Builder $query, EntityNotice $notice): Builder
    {
        $path = $notice->path;

        return $path instanceof AssessmentPath && $path->isReviewed() ? $query->followingPath($path) : $query;
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
