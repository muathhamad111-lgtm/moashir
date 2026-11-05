<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Platform\HousekeepingTask;
use App\Models\Audit\Audit;
use App\Models\Contact\ContactMessage;
use App\Models\Contact\ContactMessageEntry;
use App\Models\Platform\HousekeepingRun;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function closedContactMessage(int $monthsAgo, array $attributes = []): ContactMessage
{
    return contactMessage([...$attributes, 'status' => ContactMessageStatus::Closed, 'closed_at' => Date::now()->subMonths($monthsAgo)->subDay()]);
}

it('purges messages closed longer ago than the retention period and records counts only', function (): void {
    $expired = closedContactMessage(25);
    $recent = closedContactMessage(23);
    $open = contactMessage(['created_at' => Date::now()->subYears(3)]);

    expect(Artisan::call('moashir:contact-messages:purge-closed'))->toBe(0)
        ->and(Artisan::output())->toContain('Purged 1 closed contact message(s) with 1 thread entr(ies) older than 24 months.');

    expect(ContactMessage::query()->pluck('id')->sort()->values()->all())->toBe([$recent->id, $open->id])
        ->and(ContactMessageEntry::query()->where('contact_message_id', $expired->id)->exists())->toBeFalse();

    $run = HousekeepingRun::query()->where('task', HousekeepingTask::ContactMessagePurge)->sole();
    expect($run->counts)->toEqual(['purged_messages' => 1, 'purged_entries' => 1, 'retention_months' => 24]);

    $audit = Audit::query()->where('event', AuditEvent::ContactMessagesPurged->value)->sole();
    expect($audit->new_values)->toEqual(['purged_messages' => 1, 'purged_entries' => 1, 'retention_months' => 24])
        ->and($audit->auditable_id)->toBe($run->id);
});

it('records an empty purge run without an audit row', function (): void {
    contactMessage();

    Artisan::call('moashir:contact-messages:purge-closed');

    expect(HousekeepingRun::query()->where('task', HousekeepingTask::ContactMessagePurge)->sole()->counts['purged_messages'])->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::ContactMessagesPurged->value)->exists())->toBeFalse();
});

it('follows the retention setting edited by the System Administrator', function (): void {
    $admin = systemAdmin();
    closedContactMessage(7);
    $kept = closedContactMessage(5);

    switchTo($admin)->getJson('/api/v1/settings/contact-messages')->assertOk()
        ->assertExactJson(['data' => ['retention_months' => 24, 'retention_limits' => ['min' => 1, 'max' => 120]], 'meta' => [], 'errors' => null]);
    switchTo($admin)->putJson('/api/v1/settings/contact-messages', ['retention_months' => 6])->assertOk()->assertJsonPath('data.retention_months', 6);

    Artisan::call('moashir:contact-messages:purge-closed');

    expect(ContactMessage::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(Audit::query()->where('event', AuditEvent::ContactSettingsUpdated->value)->sole()->new_values)->toBe(['contact.retention_months' => 6]);
});

it('protects and validates the retention setting', function (): void {
    switchTo(contentOfficer())->getJson('/api/v1/settings/contact-messages')->assertForbidden();
    switchTo(contentOfficer())->putJson('/api/v1/settings/contact-messages', ['retention_months' => 12])->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/settings/contact-messages')->assertForbidden();
    switchTo(systemAdmin())->putJson('/api/v1/settings/contact-messages', ['retention_months' => 0])->assertUnprocessable()->assertJsonValidationErrors('retention_months');
    switchTo(systemAdmin())->putJson('/api/v1/settings/contact-messages', ['retention_months' => 121])->assertUnprocessable();
    switchTo(systemAdmin())->putJson('/api/v1/settings/contact-messages', [])->assertUnprocessable();

    $superAdmin = superAdmin();
    switchTo($superAdmin)->putJson('/api/v1/settings/contact-messages', ['retention_months' => 12])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    switchTo($superAdmin)->putJson('/api/v1/settings/contact-messages', ['retention_months' => 12, 'reason' => CONTACT_REASON])->assertOk();
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('erases every message of an address on a data-subject request with a counts-only audit', function (): void {
    $privacyOfficer = systemAdmin();
    contactMessage(['sender_email' => 'sara.alabdullah@example.sa']);
    closedContactMessage(1, ['sender_email' => 'sara.alabdullah@example.sa']);
    $other = contactMessage(['sender_email' => 'nora.alqahtani@example.sa']);

    switchTo($privacyOfficer)->postJson('/api/v1/privacy/contact-messages/erase', ['email' => 'Sara.AlAbdullah@example.sa', 'reason' => 'طلب حذف بيانات من صاحبة البيانات عبر البريد الرسمي.'])->assertOk()
        ->assertExactJson(['data' => ['erased_messages' => 2, 'erased_entries' => 2], 'meta' => [], 'errors' => null]);

    expect(ContactMessage::query()->pluck('id')->all())->toBe([$other->id]);

    $audit = Audit::query()->where('event', AuditEvent::ContactMessagesErased->value)->sole();
    expect($audit->new_values)->toEqual(['erased_messages' => 2, 'erased_entries' => 2, 'email' => 's***@example.sa'])
        ->and($audit->reason)->toBe('طلب حذف بيانات من صاحبة البيانات عبر البريد الرسمي.')
        ->and(HousekeepingRun::query()->where('task', HousekeepingTask::ContactMessageErasure)->exists())->toBeTrue();
});

it('exports the messages of an address on a data-subject request', function (): void {
    contactMessage(['sender_email' => 'sara.alabdullah@example.sa', 'sender_mobile' => '+966551234567']);
    contactMessage(['sender_email' => 'nora.alqahtani@example.sa']);

    $package = switchTo(systemAdmin())->postJson('/api/v1/privacy/contact-messages/export', ['email' => 'sara.alabdullah@example.sa', 'reason' => 'طلب اطلاع على البيانات من صاحبة البيانات.'])->assertOk()
        ->assertJsonPath('data.email', 'sara.alabdullah@example.sa')
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.sender_mobile', '+966551234567')
        ->assertJsonPath('data.messages.0.entries.0.body', CONTACT_MESSAGE_TEXT)
        ->json('data');

    expect($package)->toHaveKey('generated_at');
    $audit = Audit::query()->where('event', AuditEvent::ContactMessagesExported->value)->sole();
    expect($audit->new_values)->toEqual(['email' => 's***@example.sa', 'messages' => 1, 'format' => 'json'])
        ->and($audit->tags)->toContain('privacy');
});

it('keeps the address tools to the holders of the data-subject permission', function (): void {
    contactMessage(['sender_email' => 'sara.alabdullah@example.sa']);
    $payload = ['email' => 'sara.alabdullah@example.sa', 'reason' => 'طلب حذف بيانات من صاحبة البيانات عبر البريد الرسمي.'];

    switchTo(contentOfficer())->postJson('/api/v1/privacy/contact-messages/erase', $payload)->assertForbidden();
    switchTo(contentOfficer())->postJson('/api/v1/privacy/contact-messages/export', $payload)->assertForbidden();
    switchTo(systemAdmin())->postJson('/api/v1/privacy/contact-messages/erase', ['email' => 'sara.alabdullah@example.sa'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    switchTo(systemAdmin())->postJson('/api/v1/privacy/contact-messages/erase', ['email' => 'not-an-address', 'reason' => $payload['reason']])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(ContactMessage::query()->count())->toBe(1);
});

it('adds the linked messages to the personal-data package of an entity', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $message = linkedContactMessage($owner);
    contactMessage(['sender_email' => 'nora.alqahtani@example.sa']);
    switchTo(contentOfficer())->postJson("/api/v1/admin/contact-messages/{$message->public_id}/notes", ['body' => 'ملاحظة داخلية خاصة بالفريق.'])->assertCreated();

    $package = switchTo(systemAdmin())->postJson("/api/v1/entities/{$entity->id}/export", ['reason' => 'طلب اطلاع على البيانات من الجهة صاحبة الحساب.'])->assertOk()->json('data');

    expect($package['contact_messages'])->toHaveCount(1)
        ->and($package['contact_messages'][0]['reference'])->toBe($message->reference)
        ->and(array_column($package['contact_messages'][0]['entries'], 'kind'))->toBe(['message']);
});

it('erases the linked messages when the entity is permanently deleted', function (): void {
    $entity = entityWithOwner();
    linkedContactMessage(ownerOf($entity));
    $guest = contactMessage();
    $superAdmin = superAdmin();

    switchTo(systemAdmin())->deleteJson("/api/v1/entities/{$entity->id}", ['reason' => 'حذف الجهة بناءً على طلبها الرسمي المؤرخ.', 'confirm_open_requests' => true, 'acknowledged_request_numbers' => []])->assertNoContent();
    switchTo($superAdmin)->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'حذف نهائي بناءً على طلب صاحب البيانات وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(ContactMessage::query()->pluck('id')->all())->toBe([$guest->id]);
});
