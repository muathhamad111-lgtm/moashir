<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Identity\PermissionKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\IdentityChangeRequest;
use App\Notifications\Entities\EntityNotification;
use App\Services\Entities\EntityNotifier;

final readonly class IdentityChangeNotifier
{
    private const string REVIEW_LINK = '/admin/identity-changes';

    private const string PROFILE_LINK = '/account';

    public function __construct(private EntityNotifier $notifier) {}

    public function toReviewers(IdentityChangeRequest $request): void
    {
        $notice = $this->inAppNotice(NotificationCode::IdentityChangeRequested, 'reviewer', $request, self::REVIEW_LINK);

        $this->notifier->toPermissionHolders(PermissionKey::UsersIdentityChangesReview, $notice, $request->user_id);
    }

    public function toRequester(IdentityChangeRequest $request): void
    {
        $audience = $request->status->value;
        $notice = $this->inAppNotice(NotificationCode::IdentityChangeDecided, $audience, $request, self::PROFILE_LINK);

        $request->user->notify(new EntityNotification($notice));
    }

    private function inAppNotice(NotificationCode $code, string $audience, IdentityChangeRequest $request, string $link): EntityNotice
    {
        $text = new NoticeText('notifications.identity.'.$code->value.'.'.$audience, ['user' => $request->user->name]);

        return new EntityNotice($code, $text->title(), $text->body(), $link, false, true, null, $text);
    }
}
