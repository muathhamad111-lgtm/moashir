<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Contact\ContactMessage;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lists only the messages the signed-in user sent while signed in', function (): void {
    $owner = ownerOf(entityWithOwner());
    $other = ownerOf(entityWithOwner());
    $mine = linkedContactMessage($owner);
    linkedContactMessage($other);
    contactMessage(['sender_email' => mb_strtolower($owner->email)]);

    switchTo($owner)->getJson('/api/v1/me/contact-messages')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->public_id)
        ->assertJsonPath('data.0.can_reply', true)
        ->assertJsonPath('meta.pagination.total', 1);

    switchTo($owner)->getJson('/api/v1/me/contact-messages?filter[status]=closed')->assertOk()->assertJsonCount(0, 'data');
    switchTo($owner)->getJson('/api/v1/me/contact-messages?filter[status]=archived')->assertUnprocessable();
});

it('shows the thread without internal notes or staff identities', function (): void {
    $owner = ownerOf(entityWithOwner());
    $handler = contentOfficer();
    $message = linkedContactMessage($owner);
    switchTo($handler)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/notes", ['body' => 'ملاحظة داخلية لا يراها المرسل.'])->assertCreated();
    switchTo($handler)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/replies", ['body' => 'تم استلام طلبكم وسنعود إليكم قريباً.'])->assertCreated();

    $detail = switchTo($owner)->getJson('/api/v1/me/contact-messages/'.$message->public_id)->assertOk()
        ->assertJsonPath('data.status', 'replied')
        ->assertJsonCount(2, 'data.entries')
        ->assertJsonPath('data.entries.0.author', 'sender')
        ->assertJsonPath('data.entries.1.author', 'team')
        ->json('data');

    expect(json_encode($detail, JSON_UNESCAPED_UNICODE))->not->toContain('ملاحظة داخلية')
        ->not->toContain($handler->name);
});

it('answers 404 for a message of another sender, for everyone including the Super Admin', function (): void {
    $message = linkedContactMessage(ownerOf(entityWithOwner()));
    $guestMessage = contactMessage();

    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/me/contact-messages/'.$message->public_id)->assertNotFound();
    switchTo(superAdmin())->getJson('/api/v1/me/contact-messages/'.$message->public_id)->assertNotFound();
    switchTo(contentOfficer())->getJson('/api/v1/me/contact-messages/'.$guestMessage->public_id)->assertNotFound();
    switchTo(ownerOf(entityWithOwner()))->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => 'متابعة على الطلب.'])->assertNotFound();
    asGuest()->getJson('/api/v1/me/contact-messages')->assertUnauthorized();
});

it('lets the sender reply on an open thread and notifies the assignee', function (): void {
    $owner = ownerOf(entityWithOwner());
    $handler = contentOfficer();
    $message = linkedContactMessage($owner);
    $message->forceFill(['assigned_to_user_id' => $handler->id, 'status' => ContactMessageStatus::Replied])->save();

    switchTo($owner)->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => 'شكراً لكم، هل يمكن تمديد مدة السداد؟'])->assertCreated()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.entries.1.author', 'sender');

    Notification::assertSentTo($handler, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::ContactFollowUpReceived);
    expect(Audit::query()->where('event', AuditEvent::ContactMessageFollowUpReceived->value)->sole()->new_values)->not->toHaveKey('body');
});

it('sends the sender follow-up of an unassigned message to every handler', function (): void {
    $owner = ownerOf(entityWithOwner());
    $admin = systemAdmin();
    $content = contentOfficer();
    $message = linkedContactMessage($owner);

    switchTo($owner)->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => 'إضافة معلومة إلى رسالتي السابقة.'])->assertCreated()
        ->assertJsonPath('data.status', 'new');

    foreach ([$admin, $content] as $handler) {
        Notification::assertSentTo($handler, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::ContactFollowUpReceived);
    }
});

it('refuses a reply on a closed thread and invalid replies', function (): void {
    $owner = ownerOf(entityWithOwner());
    $message = linkedContactMessage($owner);
    $message->forceFill(['status' => ContactMessageStatus::Closed, 'closed_at' => Date::now()])->save();

    switchTo($owner)->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => 'متابعة بعد الإغلاق.'])->assertConflict()
        ->assertJsonPath('code', 'contact.message_closed');
    switchTo($owner)->getJson('/api/v1/me/contact-messages/'.$message->public_id)->assertOk()->assertJsonPath('data.can_reply', false);

    $open = linkedContactMessage($owner);
    switchTo($owner)->postJson("/api/v1/me/contact-messages/{$open->public_id}/replies", ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    switchTo($owner)->postJson("/api/v1/me/contact-messages/{$open->public_id}/replies", ['body' => str_repeat('ن', 5001)])->assertUnprocessable();

    expect(ContactMessage::query()->whereKey($message->id)->sole()->status)->toBe(ContactMessageStatus::Closed);
});

it('limits the follow-ups of a sender', function (): void {
    $owner = ownerOf(entityWithOwner());
    $message = linkedContactMessage($owner);

    foreach (range(1, 10) as $attempt) {
        switchTo($owner)->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => "متابعة رقم {$attempt}"])->assertCreated();
    }

    switchTo($owner)->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => 'متابعة إضافية'])->assertTooManyRequests();
});
