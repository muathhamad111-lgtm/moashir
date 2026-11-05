<?php

declare(strict_types=1);

namespace App\Listeners\Contact;

use App\Events\Contact\ContactMessageReceived;
use App\Services\Contact\ContactMessageNotifier;

final readonly class NotifyContactMessageReceived
{
    public function __construct(private ContactMessageNotifier $notifier) {}

    public function handle(ContactMessageReceived $event): void
    {
        $this->notifier->acknowledge($event->message);
        $this->notifier->announceToHandlers($event->message);
    }
}
