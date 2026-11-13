<?php

declare(strict_types=1);

namespace App\Data\Mail;

use Illuminate\Notifications\Notification;

final readonly class MailSample
{
    public function __construct(public string $name, public Notification $notification, public object $notifiable) {}
}
