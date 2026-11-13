<?php

declare(strict_types=1);

namespace App\Events\Contact;

use App\Models\Contact\ContactMessage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class ContactMessageAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ContactMessage $message, public int $assigneeId, public ?int $actorId) {}
}
