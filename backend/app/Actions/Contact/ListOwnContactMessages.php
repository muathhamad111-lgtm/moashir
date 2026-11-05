<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Enums\Contact\ContactMessageStatus;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOwnContactMessages
{
    /**
     * @param  list<ContactMessageStatus>  $statuses
     * @return LengthAwarePaginator<int, ContactMessage>
     */
    public function handle(User $sender, array $statuses, int $perPage): LengthAwarePaginator
    {
        $query = ContactMessage::query()->sentBy($sender->id)->withSenderPreview();

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        return $query->orderByDesc('last_activity_at')->orderByDesc('id')->paginate($perPage);
    }
}
