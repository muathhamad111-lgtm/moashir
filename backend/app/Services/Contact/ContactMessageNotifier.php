<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Data\Contact\ContactSenderNotice;
use App\Enums\Identity\PermissionKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Identity\User;
use App\Notifications\Contact\ContactAcknowledgementNotification;
use App\Notifications\Contact\ContactReplyNotification;
use App\Services\Entities\EntityNotifier;
use App\Support\Contact\ContactLinks;
use App\Support\Contact\ContactNotices;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

final readonly class ContactMessageNotifier
{
    public function __construct(private EntityNotifier $notifier) {}

    public function acknowledge(ContactMessage $message): void
    {
        $this->toSender($message, new ContactAcknowledgementNotification($this->senderNotice($message, null)));
    }

    public function announceToHandlers(ContactMessage $message): void
    {
        $this->notifier->toPermissionHolders(
            PermissionKey::ContactMessagesManage,
            ContactNotices::forHandlers(NotificationCode::ContactMessageReceived, $message),
            $message->user_id,
        );
    }

    public function sendReply(ContactMessage $message, ContactMessageEntry $reply): void
    {
        $this->toSender($message, new ContactReplyNotification($this->senderNotice($message, $reply->body)));
    }

    public function announceFollowUp(ContactMessage $message): void
    {
        $notice = ContactNotices::forHandlers(NotificationCode::ContactFollowUpReceived, $message);

        if ($message->assigned_to_user_id !== null) {
            $this->notifier->toStaffUser($message->assigned_to_user_id, $notice);

            return;
        }

        $this->notifier->toPermissionHolders(PermissionKey::ContactMessagesManage, $notice, $message->user_id);
    }

    public function announceAssignment(ContactMessage $message, int $assigneeId, ?int $actorId): void
    {
        if ($assigneeId !== $actorId) {
            $this->notifier->toStaffUser($assigneeId, ContactNotices::forHandlers(NotificationCode::ContactMessageAssigned, $message));
        }
    }

    private function senderNotice(ContactMessage $message, ?string $replyBody): ContactSenderNotice
    {
        return new ContactSenderNotice($message->reference, ContactNotices::enquiryTypeOf($message), ContactLinks::sender($message), $replyBody);
    }

    private function toSender(ContactMessage $message, BaseNotification $notification): void
    {
        $sender = $message->user_id === null ? null : User::query()->find($message->user_id);

        if ($sender instanceof User) {
            $sender->notify($notification);

            return;
        }

        Notification::route('mail', $message->sender_email)->notify($notification->locale($message->locale->value));
    }
}
