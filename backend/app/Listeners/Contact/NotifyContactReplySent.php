<?php

declare(strict_types=1);

namespace App\Listeners\Contact;

use App\Events\Contact\ContactReplySent;
use App\Services\Contact\ContactMessageNotifier;

final readonly class NotifyContactReplySent
{
    public function __construct(private ContactMessageNotifier $notifier) {}

    public function handle(ContactReplySent $event): void
    {
        $this->notifier->sendReply($event->message, $event->reply);
    }
}
