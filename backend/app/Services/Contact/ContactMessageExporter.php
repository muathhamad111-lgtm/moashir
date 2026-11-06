<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Enums\Contact\ContactEntryKind;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ContactMessageExporter
{
    /**
     * @param  Builder<ContactMessage>  $messages
     * @return list<array<string, mixed>>
     */
    public function rows(Builder $messages): array
    {
        return array_values($messages
            ->with(['entries' => static fn (HasMany $entries): HasMany => $entries->whereIn('kind', ContactEntryKind::senderVisible())])
            ->orderBy('id')
            ->get()
            ->map(fn (ContactMessage $message): array => $this->row($message))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ContactMessage $message): array
    {
        return [
            'reference' => $message->reference,
            'enquiry_type' => $message->enquiry_type->value,
            'status' => $message->status->value,
            'sender_name' => $message->sender_name,
            'sender_email' => $message->sender_email,
            'sender_mobile' => $message->sender_mobile,
            'organization_name' => $message->organization_name,
            'privacy_consent_at' => $message->privacy_consent_at->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
            'closed_at' => $message->closed_at?->toIso8601String(),
            'entries' => array_values($message->entries->map(static fn (ContactMessageEntry $entry): array => [
                'kind' => $entry->kind->value,
                'body' => $entry->body,
                'created_at' => $entry->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
