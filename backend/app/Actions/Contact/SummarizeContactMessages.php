<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Enums\Contact\ContactMessageStatus;
use App\Models\Contact\ContactMessage;
use App\Support\StatusCounts;

final class SummarizeContactMessages
{
    /**
     * @return array<string, int>
     */
    public function handle(): array
    {
        return StatusCounts::of(ContactMessage::query(), ContactMessageStatus::cases());
    }
}
