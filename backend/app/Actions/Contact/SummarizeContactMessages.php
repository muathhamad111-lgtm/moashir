<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Models\Contact\ContactMessage;
use App\Services\Contact\ContactInboxStatusQuery;

final readonly class SummarizeContactMessages
{
    public function __construct(private ContactInboxStatusQuery $statuses) {}

    /**
     * The inbox count of every derived status, closed messages included (closing keeps the status).
     *
     * @return array<string, int>
     */
    public function handle(): array
    {
        return $this->statuses->counts(ContactMessage::query());
    }
}
