<?php

declare(strict_types=1);

use App\Enums\Contact\ContactInboxStatus;
use App\Enums\Contact\ContactMessageStatus;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-25 08:00:00');
});

function inboxPath(ContactMessage $message, string $action = ''): string
{
    return '/api/v1/admin/contact-messages/'.$message->public_id.($action === '' ? '' : '/'.$action);
}

function inboxStatusOf(User $handler, ContactMessage $message): string
{
    return switchTo($handler)->getJson(inboxPath($message))->assertOk()->json('data.inbox_status');
}

function inboxList(User $handler, string $query = ''): TestResponse
{
    return switchTo($handler)->getJson('/api/v1/admin/contact-messages'.$query)->assertOk();
}

function laterBy(int $minutes): void
{
    Date::setTestNow(Date::now()->addMinutes($minutes));
}

it('moves a message through every inbox status as the team and the sender write', function (): void {
    $handler = contentOfficer();
    $owner = ownerOf(entityWithOwner());
    $message = linkedContactMessage($owner);
    $follow = static fn (string $body): TestResponse => switchTo($owner)->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => $body])->assertCreated();

    expect(inboxStatusOf($handler, $message))->toBe('new');

    laterBy(5);
    $follow('أضيف معلومة: الطلب رقم 12.');
    expect(inboxStatusOf($handler, $message))->toBe('new');

    switchTo($handler)->postJson(inboxPath($message, 'assign'), ['assignee_id' => $handler->id])->assertOk()->assertJsonPath('data.inbox_status', 'assigned');
    switchTo($handler)->postJson(inboxPath($message, 'assign'), ['assignee_id' => null])->assertOk()->assertJsonPath('data.inbox_status', 'new');
    switchTo($handler)->postJson(inboxPath($message, 'assign'), ['assignee_id' => $handler->id])->assertOk();

    laterBy(5);
    switchTo($handler)->postJson(inboxPath($message, 'notes'), ['body' => 'نراجع الطلب مع المالية.'])->assertCreated()->assertJsonPath('data.inbox_status', 'assigned');

    laterBy(5);
    switchTo($handler)->postJson(inboxPath($message, 'replies'), ['body' => 'وصلنا طلبكم ونراجعه.'])->assertCreated()->assertJsonPath('data.inbox_status', 'replied');

    laterBy(5);
    $follow('هل من جديد؟');
    expect(inboxStatusOf($handler, $message))->toBe('awaiting_reply');

    laterBy(5);
    switchTo($handler)->postJson(inboxPath($message, 'notes'), ['body' => 'ننتظر رد المالية.'])->assertCreated()->assertJsonPath('data.inbox_status', 'awaiting_reply');
    switchTo($handler)->postJson(inboxPath($message, 'replies'), ['body' => 'أنجزنا الطلب.'])->assertCreated()->assertJsonPath('data.inbox_status', 'replied');
});

it('keeps the inbox status when a message is closed and reopened', function (): void {
    $handler = contentOfficer();
    $message = contactMessage(['assigned_to_user_id' => $handler->id]);

    switchTo($handler)->postJson(inboxPath($message, 'close'))->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.inbox_status', 'assigned');
    switchTo($handler)->postJson(inboxPath($message, 'reopen'))->assertOk()->assertJsonPath('data.inbox_status', 'assigned');
});

it('refuses both a reply and an internal note on a closed message', function (): void {
    $handler = contentOfficer();
    $message = contactMessage();
    switchTo($handler)->postJson(inboxPath($message, 'close'))->assertOk();

    switchTo($handler)->postJson(inboxPath($message, 'notes'), ['body' => 'ملاحظة بعد الإغلاق.'])->assertConflict()->assertJsonPath('code', 'contact.message_closed');
    switchTo($handler)->postJson(inboxPath($message, 'replies'), ['body' => 'رد بعد الإغلاق.'])->assertConflict()->assertJsonPath('code', 'contact.message_closed');
    expect($message->entries()->count())->toBe(1);
});

it('filters and counts the inbox by the derived status, closed messages included', function (): void {
    $handler = contentOfficer();
    $new = contactMessage();
    $assigned = contactMessage(['assigned_to_user_id' => $handler->id]);
    $replied = contactMessage(['replied_at' => Date::now()->addMinute(), 'status' => ContactMessageStatus::Replied]);
    $awaiting = contactMessage(['replied_at' => Date::now()->subMinute(), 'status' => ContactMessageStatus::InProgress]);
    $closed = contactMessage(['replied_at' => Date::now()->addMinute()]);
    $closed->forceFill(['status' => ContactMessageStatus::Closed, 'closed_at' => Date::now()])->save();
    $expected = ['new' => [$new], 'assigned' => [$assigned], 'replied' => [$replied, $closed], 'awaiting_reply' => [$awaiting]];

    inboxList($handler)->assertJsonPath('meta.stats', ['new' => 1, 'assigned' => 1, 'replied' => 2, 'awaiting_reply' => 1]);

    foreach ($expected as $status => $messages) {
        $rows = inboxList($handler, '?filter[inbox_status]='.$status)->json('data');
        $ids = array_map(static fn (ContactMessage $message): string => $message->public_id, $messages);

        expect(array_column($rows, 'id'))->toEqualCanonicalizing($ids)
            ->and(array_unique(array_column($rows, 'inbox_status')))->toBe([$status]);
    }
});

it('shows the opening of the first sender message as the excerpt of a list row', function (): void {
    $message = contactMessage();

    $row = collect(inboxList(contentOfficer())->json('data'))->firstWhere('id', $message->public_id);

    expect($row['excerpt'])->toBe(mb_substr(CONTACT_MESSAGE_TEXT, 0, 120))
        ->and($row['inbox_status'])->toBe(ContactInboxStatus::New->value);
});

it('refuses an inbox status filter outside the four and the stored status filter', function (string $filter): void {
    switchTo(contentOfficer())->getJson('/api/v1/admin/contact-messages?'.$filter)->assertUnprocessable();
})->with(['unknown status' => ['filter[inbox_status]=closed'], 'stored status' => ['filter[status]=new']]);

it('refuses the status filter, the stats and notes to users without the inbox permission', function (): void {
    $message = contactMessage();

    switchTo(financeOfficer())->getJson('/api/v1/admin/contact-messages?filter[inbox_status]=new')->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/admin/contact-messages?filter[inbox_status]=new')->assertForbidden();
    switchTo(criteriaOfficer())->postJson(inboxPath($message, 'notes'), ['body' => 'ملاحظة غير مصرح بها.'])->assertForbidden();
    asGuest()->getJson('/api/v1/admin/contact-messages?filter[inbox_status]=new')->assertUnauthorized();
});

it('counts only the open new messages in the action center', function (): void {
    $handler = contentOfficer();
    contactMessage();
    contactMessage(['assigned_to_user_id' => $handler->id]);
    contactMessage(['replied_at' => Date::now()->subMinute()]);
    contactMessage()->forceFill(['status' => ContactMessageStatus::Closed, 'closed_at' => Date::now()])->save();

    $items = collect(switchTo($handler)->getJson('/api/v1/dashboard/action-center')->assertOk()->json('data'))->keyBy('key');

    expect($items->get('answer_contact_messages')['count'] ?? null)->toBe(1);
});
