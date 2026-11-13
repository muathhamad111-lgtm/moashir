<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use App\Notifications\Contracts\DeliversInApp;

final class InAppChannel
{
    public function send(User $notifiable, DeliversInApp $notification): void
    {
        $message = $notification->toInApp($notifiable);

        Notification::query()->create([
            'user_id' => $notifiable->id,
            'code' => $message->code->value,
            'category' => $message->code->category(),
            'title' => $message->title,
            'body' => $message->body,
            'link' => $message->link,
            'path' => $message->path,
        ]);
    }
}
