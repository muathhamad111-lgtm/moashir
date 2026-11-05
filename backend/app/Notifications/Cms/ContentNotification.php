<?php

declare(strict_types=1);

namespace App\Notifications\Cms;

use App\Data\Cms\ContentNotice;
use App\Data\Notifications\InAppMessage;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Contracts\DeliversInApp;
use App\Notifications\QueuedNotification;

final class ContentNotification extends QueuedNotification implements DeliversInApp
{
    public function __construct(public readonly ContentNotice $notice)
    {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return [InAppChannel::class];
    }

    public function toInApp(User $notifiable): InAppMessage
    {
        return new InAppMessage($this->notice->code, $this->notice->title, $this->notice->body, $this->notice->link);
    }
}
