<?php

declare(strict_types=1);

use App\Data\Notifications\NotificationType;
use App\Enums\Notifications\NotificationAudience;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Support\Notifications\NotificationTypeDefinitions;

it('defines every notification code exactly once', function (): void {
    expect(array_keys(NotificationTypeDefinitions::TYPES))->toEqualCanonicalizing(NotificationCode::values());
});

it('never lets in-app delivery be turned off', function (): void {
    foreach ((new NotificationTypeCatalog)->all() as $type) {
        expect($type->allowsOptOut(NotificationChannel::InApp))->toBeFalse();
    }
});

it('keeps security, account, payment and integration notices mandatory', function (string $module): void {
    $types = array_filter((new NotificationTypeCatalog)->all(), static fn (NotificationType $type): bool => $type->code->module() === $module);

    expect($types)->not->toBeEmpty();

    foreach ($types as $type) {
        expect($type->configurableChannels())->toBe([]);
    }
})->with(['SEC', 'USR', 'PAY', 'INT']);

it('sends system and integration notices only to staff, never to entities', function (): void {
    foreach ((new NotificationTypeCatalog)->all() as $type) {
        if (in_array($type->code->module(), ['SEC', 'INT', 'AI', 'STD', 'CMS', 'CYC', 'FIN'], true)
            && $type->code !== NotificationCode::CriteriaEditedWithScope) {
            expect($type->audiences)->not->toContain(NotificationAudience::Entity);
        }
    }
});

it('maps audiences to the roles that receive them', function (): void {
    expect(NotificationAudience::CommitteeAssessor->roleKey()?->value)->toBe('assessor')
        ->and(NotificationAudience::SuperAdmin->roleKey())->toBeNull()
        ->and(NotificationAudience::FinanceOfficer->roleKey()?->value)->toBe('finance_officer');
});

it('reports the channels a type is delivered on and which ones a user may turn off', function (): void {
    $type = (new NotificationTypeCatalog)->of(NotificationCode::AccreditedAssigned);

    expect($type->channels)->toBe([NotificationChannel::InApp, NotificationChannel::Email])
        ->and($type->configurableChannels())->toBe([NotificationChannel::Email])
        ->and($type->reaches([NotificationAudience::Assessor]))->toBeTrue()
        ->and($type->reaches([NotificationAudience::Entity]))->toBeFalse()
        ->and(NotificationCode::AccreditedAssigned->module())->toBe('ACC');
});

it('maps Laravel delivery channels to notification channels', function (): void {
    expect(NotificationChannel::fromDeliveryChannel('mail'))->toBe(NotificationChannel::Email)
        ->and(NotificationChannel::fromDeliveryChannel('database'))->toBeNull()
        ->and(fn (): NotificationChannel => NotificationChannel::fromLetter('X'))->toThrow(ValueError::class);
});
