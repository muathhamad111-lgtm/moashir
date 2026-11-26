<?php

declare(strict_types=1);

use App\Enums\Contact\ContactInboxStatus;
use Illuminate\Support\Facades\Date;

it('derives the inbox status from the assignee and the last reply and sender message times', function (bool $assigned, ?string $repliedAt, ?string $senderAt, ContactInboxStatus $expected): void {
    $at = static fn (?string $time) => $time === null ? null : Date::parse('2026-11-25 '.$time);

    expect(ContactInboxStatus::derive($assigned, $at($repliedAt), $at($senderAt)))->toBe($expected);
})->with([
    'no reply, nobody assigned' => [false, null, '08:00', ContactInboxStatus::New],
    'no reply, a follow-up, nobody assigned' => [false, null, '09:00', ContactInboxStatus::New],
    'no reply, assigned' => [true, null, '08:00', ContactInboxStatus::Assigned],
    'replied after the sender' => [true, '10:00', '08:00', ContactInboxStatus::Replied],
    'replied, no sender time known' => [false, '10:00', null, ContactInboxStatus::Replied],
    'replied at the same moment' => [true, '10:00', '10:00', ContactInboxStatus::Replied],
    'the sender wrote after the reply' => [true, '10:00', '10:05', ContactInboxStatus::AwaitingReply],
    'unassigned after a reply, sender wrote again' => [false, '10:00', '11:00', ContactInboxStatus::AwaitingReply],
]);

it('lists the four values the API filters by', function (): void {
    expect(ContactInboxStatus::values())->toBe(['new', 'assigned', 'replied', 'awaiting_reply']);
});
