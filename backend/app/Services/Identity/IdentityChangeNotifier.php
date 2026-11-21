<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Identity\IdentityChangeKind;
use App\Enums\Identity\PermissionKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Entities\EntityNotifier;

final readonly class IdentityChangeNotifier
{
    private const string REVIEW_LINK = '/admin/identity-changes';

    private const string STAFF_PROFILE_LINK = '/account';

    private const string ENTITY_PROFILE_LINK = '/entity/profile';

    private const string USERS_LINK = '/admin/settings/users';

    public function __construct(private EntityNotifier $notifier) {}

    public function toReviewers(IdentityChangeRequest $request): void
    {
        $audience = $request->kind === IdentityChangeKind::FirstEntry ? 'first_entry' : 'reviewer';
        $notice = $this->inAppNotice(NotificationCode::IdentityChangeRequested, $audience, $request->user, self::REVIEW_LINK);

        $this->notifier->toPermissionHolders(PermissionKey::UsersIdentityChangesReview, $notice, $request->user_id);
    }

    public function toRequester(IdentityChangeRequest $request): void
    {
        $link = $request->user->isEntityUser() ? self::ENTITY_PROFILE_LINK : self::STAFF_PROFILE_LINK;
        $notice = $this->inAppNotice(NotificationCode::IdentityChangeDecided, $request->status->value, $request->user, $link);

        $request->user->notify(new EntityNotification($notice));
    }

    public function nationalIdChangedAtAcceptance(User $invitee): void
    {
        $notice = $this->inAppNotice(NotificationCode::NationalIdChangedAtAcceptance, 'reviewer', $invitee, self::USERS_LINK);

        $this->notifier->toPermissionHolders(PermissionKey::UsersIdentityChangesReview, $notice, $invitee->id);
    }

    private function inAppNotice(NotificationCode $code, string $audience, User $subject, string $link): EntityNotice
    {
        $text = new NoticeText('notifications.identity.'.$code->value.'.'.$audience, ['user' => $subject->name]);

        return new EntityNotice($code, $text->title(), $text->body(), $link, false, true, null, $text);
    }
}
