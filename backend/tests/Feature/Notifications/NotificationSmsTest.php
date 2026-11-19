<?php

declare(strict_types=1);

use App\Data\Entities\EntityNotice;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Entities\EntityNotification;

const NOTIFICATION_SMS = '/api/v1/settings/notifications';

const REGISTERED_SMS = ['ar' => 'سجّلت :entity برقم :reference', 'en' => ':entity registered as :reference'];

function registeredParts(array $sms = REGISTERED_SMS): array
{
    $text = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_SMS.'/types/REG-01')->json('data.texts'))->firstWhere('key', 'entities.REG-01.staff');
    $parts = array_map(static fn (array $part): array => $part['current'], $text['parts']);

    return [...$parts, 'sms' => $sms];
}

function registeredSmsNotice(): EntityNotification
{
    return new EntityNotification(EntityNotice::for(NotificationCode::EntityRegistered, EntityNotice::FOR_STAFF, ['entity' => 'جمعية الأمل الخيرية', 'reference' => 'ENT-000001'], 'admin/entities/1'));
}

function phoneHolder(): User
{
    $user = staffUser(RoleKey::SystemAdmin);
    $user->forceFill(['phone' => '0551234567'])->save();

    return $user->refresh();
}

function approveMsegat(array $bodies): void
{
    configureMsegat(['template_restricted' => true], [
        'is_active' => true,
        'provider_refs' => ['templates_loaded_at' => now()->toIso8601String(), 'approved_templates' => array_map(static fn (string $body): array => ['id' => '1', 'title' => 'T', 'body' => $body], $bodies)],
    ]);
}

it('keeps a notice off SMS until the administrator writes its SMS text and switches SMS on', function (): void {
    $recipient = phoneHolder();
    expect(registeredSmsNotice()->via($recipient))->not->toContain(SmsChannel::class);

    switchTo(systemAdmin())->putJson(NOTIFICATION_SMS.'/texts/entities.REG-01.staff', ['parts' => registeredParts()])->assertOk();
    expect(registeredSmsNotice()->via($recipient))->not->toContain(SmsChannel::class);

    switchTo(systemAdmin())->patchJson(NOTIFICATION_SMS.'/types/REG-01/channels', ['channels' => ['sms' => true]])->assertOk();

    $notification = registeredSmsNotice();
    expect($notification->via($recipient))->toBe(['mail', InAppChannel::class, SmsChannel::class]);
    app()->setLocale('ar');
    expect($notification->toSms($recipient))->toBe('سجّلت جمعية الأمل الخيرية برقم ENT-000001');
});

it('never texts a recipient without a phone', function (): void {
    switchTo(systemAdmin())->putJson(NOTIFICATION_SMS.'/texts/entities.REG-01.staff', ['parts' => registeredParts()])->assertOk();
    switchTo(systemAdmin())->patchJson(NOTIFICATION_SMS.'/types/REG-01/channels', ['channels' => ['sms' => true]])->assertOk();

    $recipient = staffUser(RoleKey::SystemAdmin);
    $recipient->forceFill(['phone' => null])->save();

    expect(registeredSmsNotice()->via($recipient->refresh()))->not->toContain(SmsChannel::class);
});

it('shows the SMS approval per language and sends only an approved text through a restricted Msegat account', function (): void {
    approveMsegat(['سجّلت {1} برقم {2}']);
    switchTo(systemAdmin())->putJson(NOTIFICATION_SMS.'/texts/entities.REG-01.staff', ['parts' => registeredParts()])->assertOk();
    switchTo(systemAdmin())->patchJson(NOTIFICATION_SMS.'/types/REG-01/channels', ['channels' => ['sms' => true]])->assertOk();

    $text = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_SMS.'/types/REG-01')->json('data.texts'))->firstWhere('key', 'entities.REG-01.staff');
    expect($text['sms']['current'])->toBe(REGISTERED_SMS)
        ->and($text['sms']['approval'])->toBe(['ar' => 'approved', 'en' => 'pending']);

    $arabic = phoneHolder();
    $arabic->forceFill(['locale' => 'ar'])->save();
    $english = phoneHolder();
    $english->forceFill(['locale' => 'en'])->save();

    expect(registeredSmsNotice()->via($arabic->refresh()))->toContain(SmsChannel::class)
        ->and(registeredSmsNotice()->via($english->refresh()))->not->toContain(SmsChannel::class);
});

it('reports that the Msegat templates are not loaded yet', function (): void {
    configureMsegat(['template_restricted' => true], ['is_active' => true]);
    switchTo(systemAdmin())->putJson(NOTIFICATION_SMS.'/texts/entities.REG-01.staff', ['parts' => registeredParts()])->assertOk();

    $text = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_SMS.'/types/REG-01')->json('data.texts'))->firstWhere('key', 'entities.REG-01.staff');

    expect($text['sms']['approval'])->toBe(['ar' => 'templates_not_loaded', 'en' => 'templates_not_loaded']);
});

it('has no SMS text until one is written and refuses one for a mandatory type', function (): void {
    $text = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_SMS.'/types/REG-01')->json('data.texts'))->firstWhere('key', 'entities.REG-01.staff');
    expect($text['sms'])->toBe(['current' => null, 'approval' => null]);

    $invitation = collect(switchTo(systemAdmin())->getJson(NOTIFICATION_SMS.'/types/USR-01')->json('data.texts'))->firstWhere('key', 'staff_invited');
    $parts = [...array_map(static fn (array $part): array => $part['current'], $invitation['parts']), 'sms' => ['ar' => 'دعوة', 'en' => 'Invitation']];

    switchTo(systemAdmin())->putJson(NOTIFICATION_SMS.'/texts/staff_invited', ['parts' => $parts])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts.sms']);
});

it('refuses an SMS text with a variable the notification does not have', function (): void {
    switchTo(systemAdmin())->putJson(NOTIFICATION_SMS.'/texts/entities.REG-01.staff', ['parts' => registeredParts(['ar' => 'رمزك :code', 'en' => 'Code :code'])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parts.sms.ar', 'parts.sms.en']);
});
