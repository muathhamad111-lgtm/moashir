<?php

declare(strict_types=1);

use App\Enums\Contact\ContactMessageStatus;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const TEAM_REPLY = 'شكراً لتواصلكم، يمكنكم رفع الشواهد بصيغة PDF بحد أقصى عشرين ميجابايت لكل ملف.';

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-04 08:00:00');
});

function replyAsTeam(ContactMessage $message, string $body = TEAM_REPLY): TestResponse
{
    return switchTo(contentOfficer())->postJson("/api/v1/admin/contact-messages/{$message->public_id}/replies", ['body' => $body])->assertCreated();
}

function myMessages(User $sender, string $query = ''): TestResponse
{
    return switchTo($sender)->getJson('/api/v1/me/contact-messages'.$query);
}

it('filters the own messages by one or several statuses and counts every status regardless of the filter', function (): void {
    $owner = ownerOf(entityWithOwner());
    $new = linkedContactMessage($owner);
    $replied = linkedContactMessage($owner);
    $closed = linkedContactMessage($owner);
    $replied->forceFill(['status' => ContactMessageStatus::Replied, 'replied_at' => Date::now()])->save();
    $closed->forceFill(['status' => ContactMessageStatus::Closed, 'closed_at' => Date::now()])->save();
    linkedContactMessage(ownerOf(entityWithOwner()));
    $counts = ['new' => 1, 'in_progress' => 0, 'replied' => 1, 'closed' => 1];

    myMessages($owner, '?filter[status]=new,in_progress,replied')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.status_counts', $counts)
        ->assertJsonPath('meta.unread_replies', 1);
    myMessages($owner, '?filter[status]=closed')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $closed->public_id)
        ->assertJsonPath('meta.status_counts', $counts);
    expect(collect(myMessages($owner)->assertOk()->json('data'))->pluck('id')->all())->toContain($new->public_id, $replied->public_id, $closed->public_id);
});

it('refuses an unknown status in the list filter', function (string $filter): void {
    myMessages(ownerOf(entityWithOwner()), '?filter[status]='.$filter)->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['filter.status']);
})->with(['unknown value' => ['archived'], 'unknown value in a list' => ['new,archived'], 'empty item' => ['new,,closed']]);

it('previews the latest entry the sender may see and never an internal note', function (): void {
    $owner = ownerOf(entityWithOwner());
    $message = linkedContactMessage($owner);

    myMessages($owner)->assertOk()
        ->assertJsonPath('data.0.preview', CONTACT_MESSAGE_TEXT)
        ->assertJsonPath('data.0.last_entry_author', 'sender')
        ->assertJsonPath('data.0.has_unread_reply', false);

    $reply = str_repeat('نرجو تحديث بيانات الجهة قبل رفع الشواهد. ', 8);
    replyAsTeam($message, $reply);
    test()->travel(1)->minutes();
    switchTo(contentOfficer())->postJson("/api/v1/admin/contact-messages/{$message->public_id}/notes", ['body' => 'ملاحظة داخلية: تحقق الفريق من الملف.'])->assertCreated();

    $row = myMessages($owner)->assertOk()->json('data.0');

    expect($row['preview'])->toBe(mb_substr(trim($reply), 0, 160))
        ->and(mb_strlen($row['preview']))->toBe(160)
        ->and($row['last_entry_author'])->toBe('team')
        ->and($row['has_unread_reply'])->toBeTrue()
        ->and(json_encode($row, JSON_UNESCAPED_UNICODE))->not->toContain('ملاحظة داخلية');
});

it('marks the replies of an own message as seen and stays idempotent', function (): void {
    $owner = ownerOf(entityWithOwner());
    $message = linkedContactMessage($owner);
    replyAsTeam($message);
    test()->travel(1)->minutes();

    myMessages($owner)->assertOk()->assertJsonPath('data.0.has_unread_reply', true)->assertJsonPath('meta.unread_replies', 1);

    switchTo($owner)->patchJson("/api/v1/me/contact-messages/{$message->public_id}/seen")->assertOk()
        ->assertJsonPath('data.id', $message->public_id)
        ->assertJsonPath('data.has_unread_reply', false)
        ->assertJsonCount(2, 'data.entries');
    switchTo($owner)->patchJson("/api/v1/me/contact-messages/{$message->public_id}/seen")->assertOk()->assertJsonPath('data.has_unread_reply', false);

    myMessages($owner)->assertOk()->assertJsonPath('meta.unread_replies', 0);

    test()->travel(1)->minutes();
    replyAsTeam($message, 'وصلنا ملفكم وسنراجعه خلال يومي عمل.');

    myMessages($owner)->assertOk()->assertJsonPath('data.0.has_unread_reply', true);
});

it('answers 404 when anyone but the sender marks a message as seen', function (): void {
    $message = linkedContactMessage(ownerOf(entityWithOwner()));
    replyAsTeam($message);
    $path = "/api/v1/me/contact-messages/{$message->public_id}/seen";

    switchTo(ownerOf(entityWithOwner()))->patchJson($path)->assertNotFound();
    switchTo(superAdmin())->patchJson($path)->assertNotFound();
    switchTo(contentOfficer())->patchJson($path)->assertNotFound();
    asGuest()->patchJson($path)->assertUnauthorized();

    expect($message->refresh()->sender_seen_at)->toBeNull();
});

it('clears the unread reply when the sender follows up', function (): void {
    $owner = ownerOf(entityWithOwner());
    $message = linkedContactMessage($owner);
    replyAsTeam($message);
    test()->travel(1)->minutes();

    switchTo($owner)->postJson("/api/v1/me/contact-messages/{$message->public_id}/replies", ['body' => 'شكراً لكم، سنرفع الملفات اليوم.'])->assertCreated()
        ->assertJsonPath('data.has_unread_reply', false)
        ->assertJsonPath('data.last_entry_author', 'sender');
});

it('reminds a signed-in sender of unread replies in the action center', function (): void {
    $owner = ownerOf(entityWithOwner());
    $first = linkedContactMessage($owner);
    $second = linkedContactMessage($owner);
    replyAsTeam($first);
    test()->travel(1)->minutes();

    $item = actionItems(boardOf($owner))->get('read_contact_replies');

    expect($item)->toBe([
        'key' => 'read_contact_replies',
        'count' => 1,
        'severity' => 'info',
        'link' => '/messages/'.$first->public_id,
        'due_at' => null,
        'context' => ['assessment_id' => null, 'payment_id' => null, 'message_id' => $first->public_id, 'reference' => $first->reference, 'remaining_subs' => null, 'percent' => null],
    ]);

    replyAsTeam($second);
    test()->travel(1)->minutes();
    $both = actionItems(boardOf($owner))->get('read_contact_replies');

    expect($both['count'] ?? null)->toBe(2)
        ->and($both['link'] ?? null)->toBe('/messages')
        ->and($both['context'] ?? null)->toBe(['assessment_id' => null, 'payment_id' => null, 'message_id' => null, 'reference' => null, 'remaining_subs' => null, 'percent' => null]);

    switchTo($owner)->patchJson("/api/v1/me/contact-messages/{$first->public_id}/seen")->assertOk();
    switchTo($owner)->patchJson("/api/v1/me/contact-messages/{$second->public_id}/seen")->assertOk();

    expect(actionItems(boardOf($owner))->has('read_contact_replies'))->toBeFalse();
});

it('returns the message id only for a submission linked to the signed-in sender', function (): void {
    $owner = ownerOf(entityWithOwner());

    $linked = switchTo($owner)->postJson('/api/v1/contact-messages', contactPayload())->assertCreated()->json('data');

    expect($linked['id'] ?? null)->toBe(ContactMessage::query()->sole()->public_id);
    submitContactAsGuest(['email' => 'huda.alharbi@example.sa'])->assertCreated()->assertJsonMissingPath('data.id');
    submitContactAsGuest(['email' => 'reem.alotaibi@example.sa', 'website' => 'https://offers.example.com'])->assertCreated()->assertJsonMissingPath('data.id');
});
