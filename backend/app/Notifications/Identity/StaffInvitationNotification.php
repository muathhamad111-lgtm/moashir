<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Models\Identity\User;
use App\Notifications\QueuedNotification;
use App\Support\FrontendUrl;
use Illuminate\Notifications\Messages\MailMessage;

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

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.staff_invited.subject'))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.staff_invited.line'))
            ->action(__('notifications.staff_invited.action'), FrontendUrl::to('auth/invitations/'.$this->token))
            ->line(__('notifications.staff_invited.expiry', ['hours' => $this->validHours]));
    }
}
