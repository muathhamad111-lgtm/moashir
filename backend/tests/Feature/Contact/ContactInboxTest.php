<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\Role;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Contact\ContactReplyNotification;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Notification::fake();
});

it('lists the inbox for the holders of the permission with filters, search and status counts', function (): void {
    $handler = contentOfficer();
    $owner = ownerOf(entityWithOwner());
    $linked = linkedContactMessage($owner);
    contactMessage(['sender_name' => 'أحمد الدوسري', 'enquiry_type' => ContactEnquiryType::TechnicalSupport, 'status' => ContactMessageStatus::Replied]);
    contactMessage(['sender_name' => 'نورة القحطاني', 'organization_name' => 'مؤسسة النماء الأهلية', 'assigned_to_user_id' => $handler->id, 'status' => ContactMessageStatus::InProgress]);
    contactMessage()->forceFill(['status' => ContactMessageStatus::Closed, 'closed_at' => Date::now()])->save();

    switchTo($handler)->getJson('/api/v1/admin/contact-messages')->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('meta.pagination.total', 4)
        ->assertJsonPath('meta.stats', ['new' => 1, 'in_progress' => 1, 'replied' => 1, 'closed' => 1]);

    $query = static fn (string $filter): array => switchTo($handler)->getJson('/api/v1/admin/contact-messages?'.$filter)->assertOk()->json('data');

    expect(array_column($query('filter[linked]=true'), 'id'))->toBe([$linked->public_id])
        ->and($query('filter[linked]=false'))->toHaveCount(3)
        ->and(array_column($query('filter[enquiry_type]=technical_support'), 'enquiry_type'))->toBe(['technical_support'])
        ->and(array_column($query('filter[status]=replied'), 'status'))->toBe(['replied'])
        ->and($query('filter[assignee]=me'))->toHaveCount(1)
        ->and($query('filter[assignee]=none'))->toHaveCount(3)
        ->and($query('search='.urlencode('احمد')))->toHaveCount(1)
        ->and($query('search='.urlencode('النماء')))->toHaveCount(1)
        ->and($query('search='.urlencode(mb_strtolower($linked->reference))))->toHaveCount(1)
        ->and($query('filter[from]='.Date::now()->setTimezone('Asia/Riyadh')->addDay()->toDateString()))->toHaveCount(0)
        ->and($query('filter[to]='.Date::now()->setTimezone('Asia/Riyadh')->toDateString()))->toHaveCount(4);

    $row = collect($query('filter[linked]=true'))->sole();
    expect($row['is_linked'])->toBeTrue()
        ->and($row['linked_user'])->toBe(['id' => $owner->id, 'name' => $owner->name])
        ->and($row['entity']['id'])->toBe($owner->entity_id)
        ->and($row['entry_count'])->toBe(1)
        ->and($row)->not->toHaveKey('entries');
});

it('refuses the inbox to users without the permission and to guests', function (): void {
    $message = contactMessage();

    switchTo(criteriaOfficer())->getJson('/api/v1/admin/contact-messages')->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/admin/contact-messages')->assertForbidden();
    switchTo(financeOfficer())->getJson('/api/v1/admin/contact-messages/'.$message->public_id)->assertForbidden();
    switchTo(financeOfficer())->postJson('/api/v1/admin/contact-messages/'.$message->public_id.'/replies', ['body' => 'شكراً لتواصلكم.'])->assertForbidden();
    asGuest()->getJson('/api/v1/admin/contact-messages')->assertUnauthorized();
});

it('validates the inbox filters', function (string $filter, string $field): void {
    switchTo(contentOfficer())->getJson('/api/v1/admin/contact-messages?'.$filter)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['filter[status]=archived', 'filter.status'],
    ['filter[enquiry_type]=invoices', 'filter.enquiry_type'],
    ['filter[from]=03-11-2026', 'filter.from'],
    ['filter[assignee]=someone', 'filter.assignee'],
    ['search=a', 'search'],
    ['per_page=101', 'per_page'],
]);

it('lets a holder grant the inbox to another role through the permission matrix', function (): void {
    $finance = financeOfficer();
    switchTo($finance)->getJson('/api/v1/admin/contact-messages')->assertForbidden();

    Role::query()->where('name', RoleKey::FinanceOfficer->value)->firstOrFail()->givePermissionTo('contact.messages.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    switchTo($finance->refresh())->getJson('/api/v1/admin/contact-messages')->assertOk();
    expect(Role::query()->where('name', RoleKey::SystemAdmin->value)->firstOrFail()->permissionKeys())->toContain('contact.messages.manage')
        ->and(Role::query()->where('name', RoleKey::ContentOfficer->value)->firstOrFail()->permissionKeys())->toContain('contact.messages.manage');
});

it('shows the full thread with internal notes and authors to a handler', function (): void {
    $handler = contentOfficer();
    $message = contactMessage(['sender_mobile' => '+966551234567']);

    switchTo($handler)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/notes", ['body' => 'تحتاج إلى تأكيد من فريق المالية قبل الرد.'])->assertCreated();

    switchTo($handler)->getJson('/api/v1/admin/contact-messages/'.$message->public_id)->assertOk()
        ->assertJsonPath('data.reference', $message->reference)
        ->assertJsonPath('data.sender.mobile', '+966551234567')
        ->assertJsonPath('data.entries.0.kind', 'message')
        ->assertJsonPath('data.entries.0.author.kind', 'sender')
        ->assertJsonPath('data.entries.1.kind', 'note')
        ->assertJsonPath('data.entries.1.author.name', $handler->name)
        ->assertJsonPath('data.status', 'new');

    $audit = Audit::query()->where('event', AuditEvent::ContactMessageNoteAdded->value)->sole();
    expect(json_encode($audit->new_values, JSON_UNESCAPED_UNICODE))->not->toContain('المالية');
});

it('assigns a message to an eligible handler, moves it in progress and notifies the assignee', function (): void {
    $admin = systemAdmin();
    $handler = contentOfficer();
    $message = contactMessage();

    switchTo($admin)->getJson('/api/v1/admin/contact-messages/assignees')->assertOk()
        ->assertJsonFragment(['id' => $handler->id, 'name' => $handler->name]);

    switchTo($admin)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/assign", ['assignee_id' => $handler->id])->assertOk()
        ->assertJsonPath('data.assignee.id', $handler->id)
        ->assertJsonPath('data.status', 'in_progress');

    Notification::assertSentTo($handler, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::ContactMessageAssigned);
    expect(Audit::query()->where('event', AuditEvent::ContactMessageAssigned->value)->sole()->new_values['assigned_to_user_id'])->toBe($handler->id);

    switchTo($admin)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/assign", ['assignee_id' => null])->assertOk()
        ->assertJsonPath('data.assignee', null)
        ->assertJsonPath('data.status', 'in_progress');
});

it('refuses an assignee who cannot handle contact messages', function (): void {
    $message = contactMessage();
    $admin = systemAdmin();

    switchTo($admin)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/assign", ['assignee_id' => criteriaOfficer()->id])->assertUnprocessable()
        ->assertJsonPath('code', 'contact.assignee_not_eligible')
        ->assertJsonValidationErrors('assignee_id');
    switchTo($admin)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/assign", ['assignee_id' => ownerOf(entityWithOwner())->id])->assertUnprocessable();
    switchTo($admin)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/assign", [])->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
});

it('e-mails a reply to a guest sender only', function (): void {
    $handler = contentOfficer();
    $message = contactMessage(['locale' => 'en']);
    $reply = "مرحباً بكم،\n\nمدة مراجعة الطلب المعتمد أربعة عشر يوماً من تاريخ الإسناد.";

    switchTo($handler)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/replies", ['body' => $reply])->assertCreated()
        ->assertJsonPath('data.status', 'replied')
        ->assertJsonPath('data.entries.1.kind', 'reply');

    Notification::assertSentOnDemand(ContactReplyNotification::class, static function (ContactReplyNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($message): bool {
        $mail = $notification->toMail($notifiable);

        return $channels === ['mail']
            && $notifiable->routes['mail'] === $message->sender_email
            && $notification->locale === 'en'
            && in_array('مدة مراجعة الطلب المعتمد أربعة عشر يوماً من تاريخ الإسناد.', $mail->quotedLines, true)
            && $mail->actionUrl === null;
    });

    $stored = ContactMessage::query()->sole();
    expect($stored->replied_at)->not->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::ContactMessageReplied->value)->sole()->new_values)->not->toHaveKey('body');
});

it('sends a reply to a linked sender by e-mail and in the portal with a deep link', function (): void {
    $owner = ownerOf(entityWithOwner());
    $message = linkedContactMessage($owner);

    switchTo(contentOfficer())->postJson("/api/v1/admin/contact-messages/{$message->public_id}/replies", ['body' => 'تم تحديث بيانات الدفع في حسابكم.'])->assertCreated();

    Notification::assertSentTo($owner, ContactReplyNotification::class, static function (ContactReplyNotification $notification, array $channels) use ($owner, $message): bool {
        $inApp = $notification->toInApp($owner);

        return $channels === ['mail', InAppChannel::class]
            && $inApp->code === NotificationCode::ContactReplySent
            && $inApp->link === '/messages/'.$message->public_id
            && ! str_contains($inApp->body->ar, 'الدفع');
    });
    Notification::assertSentOnDemandTimes(ContactReplyNotification::class, 0);
});

it('closes and reopens a message and refuses illegal transitions', function (): void {
    $handler = contentOfficer();
    $message = contactMessage();
    $path = "/api/v1/admin/contact-messages/{$message->public_id}";

    switchTo($handler)->postJson("{$path}/reopen")->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo($handler)->postJson("{$path}/close", ['reason' => 'تمت الإجابة عن الاستفسار هاتفياً.'])->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_by.id', $handler->id);
    switchTo($handler)->postJson("{$path}/close")->assertConflict();
    switchTo($handler)->postJson("{$path}/replies", ['body' => 'رد بعد الإغلاق.'])->assertConflict()->assertJsonPath('code', 'contact.message_closed');
    switchTo($handler)->postJson("{$path}/assign", ['assignee_id' => $handler->id])->assertConflict();
    switchTo($handler)->postJson("{$path}/reopen")->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.closed_at', null);

    expect(Audit::query()->where('event', AuditEvent::ContactMessageClosed->value)->sole()->reason)->toBe('تمت الإجابة عن الاستفسار هاتفياً.')
        ->and(Audit::query()->where('event', AuditEvent::ContactMessageReopened->value)->count())->toBe(1);
});

it('validates replies and notes', function (array $payload): void {
    $message = contactMessage();

    switchTo(contentOfficer())->postJson("/api/v1/admin/contact-messages/{$message->public_id}/replies", $payload)->assertUnprocessable();
    switchTo(contentOfficer())->postJson("/api/v1/admin/contact-messages/{$message->public_id}/notes", $payload)->assertUnprocessable();
})->with([
    'empty' => [[]],
    'too short' => [['body' => 'ن']],
    'too long' => [['body' => str_repeat('ن', 5001)]],
    'short reason' => [['body' => 'رد مناسب للمرسل.', 'reason' => 'قصير']],
]);

it('answers 404 for an unknown message', function (): void {
    switchTo(contentOfficer())->getJson('/api/v1/admin/contact-messages/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')->assertNotFound();
});

it('requires a reason from the Super Admin and raises the security alert', function (): void {
    $message = contactMessage();
    $superAdmin = superAdmin();

    switchTo($superAdmin)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/replies", ['body' => 'رد من إدارة المنصة.'])->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required');
    switchTo($superAdmin)->postJson("/api/v1/admin/contact-messages/{$message->public_id}/replies", ['body' => 'رد من إدارة المنصة.', 'reason' => CONTACT_REASON])->assertCreated();

    expect(Audit::query()->where('event', AuditEvent::ContactMessageReplied->value)->sole()->reason)->toBe(CONTACT_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('adds the new messages to the action center of the handlers', function (): void {
    contactMessage();
    contactMessage(['status' => ContactMessageStatus::Replied]);

    $items = collect(switchTo(contentOfficer())->getJson('/api/v1/dashboard/action-center')->assertOk()->json('data'))->keyBy('key');

    expect($items->get('answer_contact_messages')['count'] ?? null)->toBe(1)
        ->and($items->get('answer_contact_messages')['link'] ?? null)->toBe('/admin/contact-messages?status=new');
    expect(collect(switchTo(criteriaOfficer())->getJson('/api/v1/dashboard/action-center')->json('data'))->pluck('key'))->not->toContain('answer_contact_messages');
});
