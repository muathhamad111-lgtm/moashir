<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Enums\Notifications\MailReason;
use App\Models\Identity\User;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\QueuedNotification;
use App\Support\FrontendUrl;

final class StaffInvitationNotification extends QueuedNotification
{
    public function __construct(private readonly string $token, private readonly int $validHours)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): BrandedMailMessage
    {
        return BrandedMailMessage::make($notifiable, MailReason::Invitation)
            ->subject(__('notifications.staff_invited.subject'))
            ->line(__('notifications.staff_invited.line'))
            ->action(__('notifications.staff_invited.action'), FrontendUrl::to('auth/invitations/'.$this->token))
            ->line(__('notifications.staff_invited.expiry', ['duration' => trans_choice('quantities.hours', $this->validHours)]));
    }
}
