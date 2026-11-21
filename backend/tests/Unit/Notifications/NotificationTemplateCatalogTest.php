<?php

declare(strict_types=1);

use App\Enums\Notifications\NotificationCode;
use App\Support\Notifications\Templates\NotificationTemplateCatalog;
use App\Support\Notifications\Templates\NotificationTextKeys;
use App\Support\Notifications\Templates\NotificationTextTemplate;
use App\Support\Notifications\Templates\TemplatePlaceholders;

/**
 * @return array<array-key, mixed>
 */
function shippedNotificationLines(string $locale): array
{
    $lines = require lang_path($locale.'/notifications.php');

    return is_array($lines) ? $lines : [];
}

function shippedTemplateCatalog(): NotificationTemplateCatalog
{
    return NotificationTemplateCatalog::fromLines(shippedNotificationLines('ar'), shippedNotificationLines('en'));
}

it('finds the placeholders of a text once each, ignoring links and times', function (): void {
    expect(TemplatePlaceholders::in('Request :number of :entity, :Number again, at 10:30 on https://moashir.example.sa'))
        ->toBe(['number', 'entity'])
        ->and(TemplatePlaceholders::missingFrom('Valid for :duration', ['duration', 'number']))->toBe(['number'])
        ->and(TemplatePlaceholders::unknownIn('Hello :name and :secret', ['name']))->toBe(['secret']);
});

it('maps text keys to their notification code by segment or by group alias', function (string $key, ?NotificationCode $code): void {
    expect(NotificationTextKeys::codeOf($key))->toBe($code);
})->with([
    'code segment' => ['entities.REG-01.entity.title', NotificationCode::EntityRegistered],
    'nested variant' => ['assessments.accredited.ACC-12.continued.entity.body', NotificationCode::from('ACC-12')],
    'invitation alias' => ['staff_invited.expiry', NotificationCode::StaffInvited],
    'two groups under one code' => ['mfa_reset.line', NotificationCode::AccountStatusChanged],
    'source of an integration alert' => ['files.quarantined.title', NotificationCode::from('INT-05')],
    'label fragment' => ['files.purposes.cms_image', null],
    'separator' => ['list_separator', null],
]);

it('groups the shipped texts into templates with their parts, defaults and variables', function (): void {
    $catalog = shippedTemplateCatalog();
    $invitation = $catalog->find('staff_invited');
    $registered = $catalog->find('entities.REG-01.entity');

    expect($invitation)->toBeInstanceOf(NotificationTextTemplate::class)
        ->and($invitation?->code)->toBe(NotificationCode::StaffInvited)
        ->and(array_keys($invitation->parts ?? []))->toBe(['subject', 'line', 'action', 'expiry'])
        ->and($invitation?->placeholders)->toBe(['duration'])
        ->and($invitation?->parts['subject']['ar'])->toBe(__('notifications.staff_invited.subject', [], 'ar'))
        ->and($registered?->partKey('title'))->toBe('entities.REG-01.entity.title')
        ->and($catalog->find('files.purposes'))->toBeNull()
        ->and($catalog->of(NotificationCode::AccountStatusChanged))->toHaveCount(3);
});

it('gives every notification type sent with a translated text at least one template', function (): void {
    $catalog = shippedTemplateCatalog();
    $covered = array_unique(array_map(static fn (NotificationTextTemplate $template): string => $template->code->value, $catalog->all()));

    expect(count($covered))->toBeGreaterThanOrEqual(110)
        ->and($covered)->toContain('USR-01', 'REG-02', 'SEC-01', 'STD-01', 'ACC-03', 'PAY-12', 'CNT-02', 'INT-04', 'INT-05');
});

it('keeps the Arabic and English variables of every shipped template in step', function (): void {
    foreach (shippedTemplateCatalog()->all() as $template) {
        foreach ($template->parts as $part => $text) {
            expect(TemplatePlaceholders::in($text['ar']))->toEqualCanonicalizing(TemplatePlaceholders::in($text['en']), "{$template->key}.{$part}");
        }
    }
});
