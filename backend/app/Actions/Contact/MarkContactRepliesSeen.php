<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Models\Contact\ContactMessage;
use Illuminate\Support\Facades\Date;

final class MarkContactRepliesSeen
{
    public function handle(ContactMessage $message): void
    {
        $message->forceFill(['sender_seen_at' => Date::now()])->save();
    }
}
