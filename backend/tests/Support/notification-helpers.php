<?php

declare(strict_types=1);

use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use App\Support\ValueObjects\LocalizedText;

/**
 * @param  array<string, mixed>  $attributes
 */
function inAppNotification(User $user, NotificationCode $code = NotificationCode::SelfAssessmentSubmitted, array $attributes = []): Notification
{
    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'code' => $code->value,
        'category' => $code->category(),
        'title' => new LocalizedText('تم إرسال التقييم الذاتي', 'Self assessment submitted'),
        'body' => new LocalizedText('تم إرسال تقييمكم الذاتي بنجاح.', 'Your self assessment was submitted.'),
        'link' => '/assessment/self',
    ]);

    if ($attributes !== []) {
        $notification->forceFill($attributes)->save();
    }

    return $notification->refresh();
}
