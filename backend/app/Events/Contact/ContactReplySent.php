<?php

declare(strict_types=1);

namespace App\Events\Contact;

use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class ContactReplySent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ContactMessage $message, public ContactMessageEntry $reply) {}
}
