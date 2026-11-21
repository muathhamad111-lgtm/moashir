<?php

declare(strict_types=1);

namespace App\Http\Resources\Contact;

use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ContactMessage $resource
 */
final class ContactMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $card = [
            'id' => $message->public_id,
            'reference' => $message->reference,
            'status' => $message->status->value,
            'enquiry_type' => $message->enquiry_type->value,
            'sender' => [
                'name' => $message->sender_name,
                'email' => $message->sender_email,
                'mobile' => $message->sender_mobile,
                'organization' => $message->organization_name,
                'locale' => $message->locale->value,
            ],
            'is_linked' => $message->isLinked(),
            'linked_user' => $this->userSummary($message->relationLoaded('sender') ? $message->sender : null),
            'entity' => $message->relationLoaded('entity') && $message->entity instanceof Entity ? EntityPresenter::summary($message->entity) : null,
            'assignee' => $this->userSummary($message->relationLoaded('assignee') ? $message->assignee : null),
            'entry_count' => $message->getAttribute('entries_count') ?? ($message->relationLoaded('entries') ? $message->entries->count() : null),
            'created_at' => $message->created_at->toIso8601String(),
            'last_activity_at' => $message->last_activity_at->toIso8601String(),
            'replied_at' => $message->replied_at?->toIso8601String(),
            'closed_at' => $message->closed_at?->toIso8601String(),
            'closed_by' => $this->userSummary($message->relationLoaded('closedBy') ? $message->closedBy : null),
        ];

        if (! $message->relationLoaded('entries')) {
            return $card;
        }

        return [...$card, 'entries' => array_values($message->entries->map(fn (ContactMessageEntry $entry): array => $this->entry($message, $entry))->all())];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(ContactMessage $message, ContactMessageEntry $entry): array
    {
        $author = $entry->kind->isWrittenBySender()
            ? ['kind' => 'sender', 'id' => $entry->author_user_id, 'name' => $message->sender_name]
            : ['kind' => 'staff', 'id' => $entry->author_user_id, 'name' => $entry->author?->name];

        return [
            'id' => $entry->id,
            'kind' => $entry->kind->value,
            'body' => $entry->body,
            'author' => $author,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userSummary(?User $user): ?array
    {
        return $user instanceof User ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
