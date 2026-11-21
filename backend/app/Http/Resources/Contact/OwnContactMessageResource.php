<?php

declare(strict_types=1);

namespace App\Http\Resources\Contact;

use App\Enums\Contact\ContactEntryKind;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Support\ScalarValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ContactMessage $resource
 */
final class OwnContactMessageResource extends JsonResource
{
    private const string SENDER = 'sender';

    private const string TEAM = 'team';

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
            'can_reply' => $message->isOpen(),
            'created_at' => $message->created_at->toIso8601String(),
            'last_activity_at' => $message->last_activity_at->toIso8601String(),
            'replied_at' => $message->replied_at?->toIso8601String(),
            'closed_at' => $message->closed_at?->toIso8601String(),
            'has_unread_reply' => $message->hasUnreadReply(),
            'preview' => $this->preview($message),
            'last_entry_author' => $this->lastEntryAuthor($message),
        ];

        if (! $message->relationLoaded('entries')) {
            return $card;
        }

        return [...$card, 'entries' => array_values($message->entries
            ->filter(static fn (ContactMessageEntry $entry): bool => $entry->kind->isVisibleToSender())
            ->map(fn (ContactMessageEntry $entry): array => [
                'id' => $entry->id,
                'kind' => $entry->kind->value,
                'body' => $entry->body,
                'author' => $this->authorOf($entry->kind),
                'created_at' => $entry->created_at->toIso8601String(),
            ])
            ->all())];
    }

    private function preview(ContactMessage $message): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', ScalarValue::string($message->getAttribute('preview'))));
    }

    private function lastEntryAuthor(ContactMessage $message): ?string
    {
        $kind = ContactEntryKind::tryFrom(ScalarValue::string($message->getAttribute('last_entry_kind')));

        return $kind instanceof ContactEntryKind ? $this->authorOf($kind) : null;
    }

    private function authorOf(ContactEntryKind $kind): string
    {
        return $kind->isWrittenBySender() ? self::SENDER : self::TEAM;
    }
}
