<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

final readonly class SecurityAlerter
{
    public function __construct(private Repository $config) {}

    public function raise(NotificationCode $code): void
    {
        $this->notify(User::query()->superAdmins()->get(), $code);
    }

    public function raiseToAdministrators(NotificationCode $code): void
    {
        $administrators = User::query()->superAdmins()->get()
            ->merge(User::query()->role(RoleKey::SystemAdmin->value)->get())
            ->unique('id');

        $this->notify($administrators, $code);
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function notify(Collection $recipients, NotificationCode $code): void
    {
        Notification::send($recipients, new SecurityAlertNotification($code));

        $mailbox = $this->config->get('moashir.security.alert_mail');

        if (is_string($mailbox) && $mailbox !== '') {
            Notification::route('mail', $mailbox)->notify(new SecurityAlertNotification($code)->locale($this->config->string('app.fallback_locale')));
        }
    }
}
