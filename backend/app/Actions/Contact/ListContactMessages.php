<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Data\Contact\ContactMessageFilters;
use App\Models\Contact\ContactMessage;
use App\Services\Contact\ContactMessageListQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListContactMessages
{
    public function __construct(private ContactMessageListQuery $query) {}

    /**
     * @return LengthAwarePaginator<int, ContactMessage>
     */
    public function handle(ContactMessageFilters $filters): LengthAwarePaginator
    {
        return $this->query->filtered($filters)
            ->with(['assignee', 'sender', 'entity', 'closedBy'])
            ->withCount('entries')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }
}
