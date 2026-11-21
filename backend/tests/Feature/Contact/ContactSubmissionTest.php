<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Identity\User;
use App\Notifications\Contact\ContactAcknowledgementNotification;
use App\Notifications\Entities\EntityNotification;
use App\Services\Identity\SessionKeys;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('accepts a guest message, returns its reference and keeps it unlinked', function (): void {
    $year = Date::now()->setTimezone('Asia/Riyadh')->year;

    submitContactAsGuest()->assertCreated()
        ->assertJsonPath('data.reference', sprintf('CNT-%d-000001', $year))
        ->assertJsonStructure(['data' => ['reference', 'created_at']]);
    submitContactAsGuest(['email' => 'nora.alqahtani@example.sa'])->assertCreated()
        ->assertJsonPath('data.reference', sprintf('CNT-%d-000002', $year));

    $message = ContactMessage::query()->orderBy('id')->firstOrFail();
    expect($message->status->value)->toBe('new')
        ->and($message->user_id)->toBeNull()
        ->and($message->entity_id)->toBeNull()
        ->and($message->sender_name)->toBe('سارة العبدالله')
        ->and($message->sender_email)->toBe('sara.alabdullah@example.sa')
        ->and($message->sender_mobile)->toBe('+966551234567')
        ->and($message->organization_name)->toBe('جمعية الأمل الخيرية')
        ->and($message->locale->value)->toBe('ar')
        ->and($message->privacy_consent_at)->not->toBeNull()
        ->and(ContactMessageEntry::query()->where('contact_message_id', $message->id)->sole()->body)->toBe(CONTACT_MESSAGE_TEXT);
});

it('stores the mobile number encrypted and never writes the message or the address to the audit log', function (): void {
    submitContactAsGuest()->assertCreated();

    $raw = DB::table('contact_messages')->value('sender_mobile');
    $audit = Audit::query()->where('event', AuditEvent::ContactMessageReceived->value)->sole();
    $values = json_encode([$audit->old_values, $audit->new_values], JSON_UNESCAPED_UNICODE);

    expect($raw)->not->toContain('551234567')
        ->and($audit->user_id)->toBeNull()
        ->and($audit->new_values)->toMatchArray(['enquiry_type' => 'accredited_assessment', 'is_linked' => false])
        ->and($values)->not->toContain('sara')
        ->and($values)->not->toContain(CONTACT_MESSAGE_TEXT);
});

it('acknowledges a guest by e-mail in the language of the request and alerts the contact handlers', function (): void {
    $admin = systemAdmin();
    $content = contentOfficer();
    $criteria = criteriaOfficer();

    asGuest()->withHeader('Accept-Language', 'en')->postJson('/api/v1/contact-messages', contactPayload())->assertCreated();

    Notification::assertSentOnDemand(ContactAcknowledgementNotification::class, static function (ContactAcknowledgementNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return $channels === ['mail']
            && $notifiable->routes['mail'] === 'sara.alabdullah@example.sa'
            && $notification->locale === 'en'
            && ! str_contains(implode(' ', $mail->introLines), CONTACT_MESSAGE_TEXT)
            && ! str_contains(implode(' ', $mail->introLines), 'سارة');
    });

    foreach ([$admin, $content] as $handler) {
        Notification::assertSentTo($handler, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::ContactMessageReceived
            && $notification->notice->link === '/admin/contact-messages/'.ContactMessage::query()->sole()->public_id
            && ! str_contains($notification->notice->body->ar, 'سارة'));
    }

    Notification::assertNotSentTo($criteria, EntityNotification::class);
    expect(ContactMessage::query()->sole()->locale->value)->toBe('en');
});

it('links a message sent while signed in to the account and takes the identity from it', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    switchTo($owner)->postJson('/api/v1/contact-messages', contactPayload([
        'name' => 'اسم آخر من النموذج',
        'email' => 'someone.else@example.sa',
        'organization' => 'جهة أخرى',
    ]))->assertCreated();

    $message = ContactMessage::query()->sole();
    expect($message->user_id)->toBe($owner->id)
        ->and($message->entity_id)->toBe($entity->id)
        ->and($message->sender_name)->toBe($owner->name)
        ->and($message->sender_email)->toBe(mb_strtolower($owner->email))
        ->and($message->organization_name)->toBe($entity->name->ar);

    Notification::assertSentTo($owner, ContactAcknowledgementNotification::class, static fn (ContactAcknowledgementNotification $notification, array $channels): bool => $channels === ['mail']
        && $notification->notice->link === '/messages/'.$message->public_id);
    Notification::assertSentOnDemandTimes(ContactAcknowledgementNotification::class, 0);
});

it('accepts a signed-in message without the identity fields', function (): void {
    $owner = ownerOf(entityWithOwner());

    switchTo($owner)->postJson('/api/v1/contact-messages', contactPayload(['name' => null, 'email' => null, 'organization' => null]))->assertCreated();

    expect(ContactMessage::query()->sole()->user_id)->toBe($owner->id);
});

it('never links a guest message whose e-mail matches an account', function (): void {
    $owner = ownerOf(entityWithOwner());

    submitContactAsGuest(['email' => $owner->email, 'name' => $owner->name])->assertCreated();

    $message = ContactMessage::query()->sole();
    expect($message->user_id)->toBeNull()
        ->and($message->entity_id)->toBeNull();

    Notification::assertNotSentTo($owner, ContactAcknowledgementNotification::class);
    Notification::assertSentOnDemand(ContactAcknowledgementNotification::class);

    switchTo($owner)->getJson('/api/v1/me/contact-messages')->assertOk()->assertJsonCount(0, 'data');
});

it('treats an expired session as a guest', function (): void {
    $owner = ownerOf(entityWithOwner());
    $stale = Date::now()->subHours(2)->getTimestamp();

    test()->actingAs($owner, 'web')
        ->withSession([SessionKeys::SIGNED_IN_AT => $stale, SessionKeys::LAST_SEEN_AT => $stale])
        ->postJson('/api/v1/contact-messages', contactPayload(['name' => null, 'email' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email']);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('validates the submission', function (array $overrides, string $field): void {
    submitContactAsGuest($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(ContactMessage::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => null], 'name'],
    'missing e-mail' => [['email' => null], 'email'],
    'invalid e-mail' => [['email' => 'not-an-address'], 'email'],
    'landline number' => [['mobile' => '0114567890'], 'mobile'],
    'foreign mobile' => [['mobile' => '+971501234567'], 'mobile'],
    'unknown enquiry type' => [['enquiry_type' => 'invoices'], 'enquiry_type'],
    'short message' => [['message' => 'قصيرة'], 'message'],
    'long message' => [['message' => str_repeat('ن', 5001)], 'message'],
    'no consent' => [['privacy_consent' => false], 'privacy_consent'],
    'null byte' => [['message' => "رسالة تحتوي على محرف غير مقبول\0 في النص"], 'message'],
]);

it('accepts the international mobile format and an empty mobile', function (): void {
    submitContactAsGuest(['mobile' => '+966551234567'])->assertCreated();
    submitContactAsGuest(['mobile' => null, 'email' => 'nora.alqahtani@example.sa'])->assertCreated();

    expect(ContactMessage::query()->orderBy('id')->pluck('sender_mobile')->all())->toBe(['+966551234567', null]);
});

it('silently drops a message whose honeypot field is filled', function (): void {
    $handler = contentOfficer();

    submitContactAsGuest(['website' => 'https://offers.example.com'])->assertCreated()
        ->assertJsonStructure(['data' => ['reference', 'created_at']]);

    expect(ContactMessage::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::ContactMessageReceived->value)->count())->toBe(0);
    Notification::assertNothingSent();
    Notification::assertNotSentTo($handler, EntityNotification::class);
});

it('limits submissions per e-mail address', function (): void {
    foreach (range(1, 3) as $attempt) {
        submitContactAsGuest()->assertCreated();
    }

    submitContactAsGuest()->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
    expect(ContactMessage::query()->count())->toBe(3);
});

it('limits submissions per IP address across addresses', function (): void {
    foreach (range(1, 5) as $attempt) {
        submitContactAsGuest(['email' => "visitor{$attempt}@example.sa"])->assertCreated();
    }

    submitContactAsGuest(['email' => 'visitor6@example.sa'])->assertTooManyRequests();
    expect(ContactMessage::query()->count())->toBe(5);
});

it('counts a signed-in sender by the account e-mail', function (): void {
    $owner = ownerOf(entityWithOwner());

    foreach (range(1, 3) as $attempt) {
        switchTo($owner)->postJson('/api/v1/contact-messages', contactPayload(['email' => "other{$attempt}@example.sa"]))->assertCreated();
    }

    switchTo($owner)->postJson('/api/v1/contact-messages', contactPayload(['email' => 'other4@example.sa']))->assertTooManyRequests();
});

it('keeps a signed-in user with pending MFA as a guest', function (): void {
    $staff = User::factory()->create(['mfa_required' => true]);

    signIn($staff)->postJson('/api/v1/contact-messages', contactPayload())->assertCreated();

    expect(ContactMessage::query()->sole()->user_id)->toBeNull();
});
