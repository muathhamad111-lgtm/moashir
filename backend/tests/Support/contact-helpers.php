<?php

declare(strict_types=1);

use App\Enums\Contact\ContactEntryKind;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Identity\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

const CONTACT_MESSAGE_TEXT = 'نرغب في معرفة متطلبات التقييم المعتمد ومدة مراجعة الطلب بعد السداد.';

const CONTACT_REASON = 'متابعة إدارية بطلب من مدير النظام لاستكمال معالجة الرسالة.';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactPayload(array $overrides = []): array
{
    return [
        'name' => 'سارة العبدالله',
        'email' => 'sara.alabdullah@example.sa',
        'mobile' => '0551234567',
        'organization' => 'جمعية الأمل الخيرية',
        'enquiry_type' => 'accredited_assessment',
        'message' => CONTACT_MESSAGE_TEXT,
        'privacy_consent' => true,
        'website' => '',
        ...$overrides,
    ];
}

function asGuest(): TestCase
{
    $case = test()->flushSession();
    app('auth')->forgetGuards();

    return $case;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function submitContactAsGuest(array $overrides = []): TestResponse
{
    return asGuest()->postJson('/api/v1/contact-messages', contactPayload($overrides));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function contactMessage(array $attributes = [], ?User $author = null): ContactMessage
{
    $message = ContactMessage::factory()->create($attributes);
    $entry = new ContactMessageEntry(['kind' => ContactEntryKind::Message, 'body' => CONTACT_MESSAGE_TEXT, 'author_user_id' => $author?->id]);
    $entry->forceFill(['contact_message_id' => $message->id])->save();

    return $message->refresh();
}

function linkedContactMessage(User $sender): ContactMessage
{
    return contactMessage([
        'user_id' => $sender->id,
        'entity_id' => $sender->entity_id,
        'sender_name' => $sender->name,
        'sender_email' => $sender->email,
    ], $sender);
}
