<?php

declare(strict_types=1);

namespace App\Listeners\Contact;

use App\Events\Contact\ContactFollowUpReceived;
use App\Services\Contact\ContactMessageNotifier;

final readonly class NotifyContactFollowUpReceived
{
    public function __construct(private ContactMessageNotifier $notifier) {}

    public function handle(ContactFollowUpReceived $event): void
    {
        $this->notifier->announceFollowUp($event->message);
    }
}
