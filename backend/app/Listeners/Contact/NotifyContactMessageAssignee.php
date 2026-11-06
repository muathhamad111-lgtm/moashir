<?php

declare(strict_types=1);

namespace App\Listeners\Contact;

use App\Events\Contact\ContactMessageAssigned;
use App\Services\Contact\ContactMessageNotifier;

final readonly class NotifyContactMessageAssignee
{
    public function __construct(private ContactMessageNotifier $notifier) {}

    public function handle(ContactMessageAssigned $event): void
    {
        $this->notifier->announceAssignment($event->message, $event->assigneeId, $event->actorId);
    }
}
