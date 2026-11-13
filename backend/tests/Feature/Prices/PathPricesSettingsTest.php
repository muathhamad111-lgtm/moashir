<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Finance\ServicePriceVersion;
use App\Models\Identity\Role;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Services\Prices\PriceVersions;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

const PATH_PRICES = '/api/v1/settings/prices';

const PRICES_REASON = 'تعديل أسعار المسارات وفق قرار اللجنة المالية للفترة القادمة.';

beforeEach(function (): void {
    Notification::fake();
    atRiyadh('2026-11-10 10:00');
});

function inForcePriceId(PaymentPurpose $purpose): ?int
{
    return app(PriceVersions::class)->inForce($purpose)?->id;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pricePayload(PaymentPurpose $purpose, array $overrides = []): array
{
    return ['price_before_vat' => '1200.00', 'availability' => 'available', 'base_version_id' => inForcePriceId($purpose), ...$overrides];
}

it('lists the four rows in order with the price in force, VAT, total, availability and limits', function (): void {
    setServicePrices('1000.00', '2500.00');

    $response = switchTo(systemAdmin())->getJson(PATH_PRICES)->assertOk()
        ->assertJsonPath('data.currency', 'SAR')
        ->assertJsonPath('data.vat_rate', '15.00')
        ->assertJsonPath('data.limits', ['price_max' => '9999999.99', 'effective_from_min' => '2026-11-11', 'effective_from_max' => '2027-11-10']);

    expect(array_column($response->json('data.rows'), 'key'))->toBe(['self', 'accredited', 'award', 'bag'])
        ->and($response->json('data.rows.0.pricing'))->toBe(['price' => null, 'is_free' => true, 'availability' => 'available'])
        ->and($response->json('data.rows.1.in_force.quote'))->toBe(['price_before_vat' => '1000.00', 'vat_rate' => '15.00', 'vat_amount' => '150.00', 'total' => '1150.00', 'currency' => 'SAR'])
        ->and($response->json('data.rows.1.in_force.is_scheduled'))->toBeFalse()
        ->and($response->json('data.rows.1.scheduled'))->toBeNull()
        ->and($response->json('data.rows.1.history_total'))->toBe(1);
});

it('sets a price at once, keeps the history with who and before, and audits the change', function (): void {
    setServicePrices('1000.00');
    $admin = systemAdmin();

    switchTo($admin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited))->assertOk()
        ->assertJsonPath('meta.changed', true)
        ->assertJsonPath('meta.cancelled_version_id', null)
        ->assertJsonPath('data.key', 'accredited')
        ->assertJsonPath('data.pricing.price.total', '1380.00')
        ->assertJsonPath('data.in_force.set_by', ['id' => $admin->id, 'name' => $admin->name]);

    switchTo($admin)->getJson(PATH_PRICES.'/accredited/history')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.state', 'in_force')
        ->assertJsonPath('data.0.before', ['price_before_vat' => '1000.00', 'availability' => 'available'])
        ->assertJsonPath('data.1.state', 'superseded')
        ->assertJsonPath('data.1.set_by', null);

    $audit = Audit::query()->where('event', AuditEvent::PricesSettingsUpdated->value)->sole();
    expect($audit->old_values['in_force']['price_before_vat'])->toBe('1000.00')
        ->and($audit->new_values['version']['price_before_vat'])->toBe('1200.00')
        ->and($audit->user_id)->toBe($admin->id);
});

it('answers unchanged for the same price and availability without a new version', function (): void {
    setServicePrices('1000.00');

    switchTo(systemAdmin())->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['price_before_vat' => '1000.00']))->assertOk()
        ->assertJsonPath('meta.changed', false);

    expect(ServicePriceVersion::query()->where('purpose', 'accredited')->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::PricesSettingsUpdated->value)->exists())->toBeFalse();
});

it('schedules a price from a Riyadh date, applies it on that day and lets a later change cancel it', function (): void {
    setServicePrices('1000.00');
    $admin = systemAdmin();

    switchTo($admin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['effective_from' => '2026-12-01']))->assertOk()
        ->assertJsonPath('data.pricing.price.price_before_vat', '1000.00')
        ->assertJsonPath('data.scheduled.effective_from', '2026-11-30T21:00:00+00:00')
        ->assertJsonPath('data.scheduled.effective_on', '2026-12-01')
        ->assertJsonPath('data.scheduled.is_scheduled', true);
    $scheduledId = ServicePriceVersion::query()->latest('id')->value('id');

    atRiyadh('2026-12-01 00:00');
    switchTo($admin)->getJson(PATH_PRICES)->assertOk()->assertJsonPath('data.rows.1.pricing.price.price_before_vat', '1200.00');

    atRiyadh('2026-11-20 10:00');
    switchTo($admin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['availability' => 'paused', 'price_before_vat' => '1000.00']))->assertOk()
        ->assertJsonPath('meta.cancelled_version_id', $scheduledId)
        ->assertJsonPath('data.scheduled', null)
        ->assertJsonPath('data.pricing.availability', 'paused');

    switchTo($admin)->getJson(PATH_PRICES.'/accredited/history')->assertOk()->assertJsonPath('data.1.state', 'cancelled');
});

it('cancels a scheduled price on request and answers 404 when none is scheduled', function (): void {
    setServicePrices('1000.00');
    $admin = systemAdmin();
    switchTo($admin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['effective_from' => '2026-12-01']))->assertOk();

    switchTo($admin)->deleteJson(PATH_PRICES.'/accredited/scheduled')->assertOk()->assertJsonPath('data.scheduled', null);
    switchTo($admin)->deleteJson(PATH_PRICES.'/accredited/scheduled')->assertNotFound()->assertJsonPath('code', 'prices.no_scheduled_version');

    expect(Audit::query()->where('event', AuditEvent::PricesSettingsUpdated->value)->count())->toBe(2);
});

it('refuses a stale edit with 409 and the current row', function (): void {
    setServicePrices('1000.00');
    $stale = inForcePriceId(PaymentPurpose::Accredited);
    $admin = systemAdmin();
    switchTo($admin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited))->assertOk();

    switchTo($admin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['base_version_id' => $stale, 'price_before_vat' => '900.00']))
        ->assertConflict()
        ->assertJsonPath('code', 'prices.stale_version')
        ->assertJsonPath('meta.row.in_force.price_before_vat', '1200.00');
});

it('accepts zero as free and rejects invalid input', function (array $payload, string $field): void {
    setServicePrices('1000.00');

    switchTo(systemAdmin())->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, $payload))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);
})->with([
    'negative price' => [['price_before_vat' => '-1.00'], 'price_before_vat'],
    'one decimal' => [['price_before_vat' => '10.5'], 'price_before_vat'],
    'above the range' => [['price_before_vat' => '10000000.00'], 'price_before_vat'],
    'unknown availability' => [['availability' => 'demo'], 'availability'],
    'today' => [['effective_from' => '2026-11-10'], 'effective_from'],
    'more than a year ahead' => [['effective_from' => '2027-11-11'], 'effective_from'],
    'not a date' => [['effective_from' => '01/12/2026'], 'effective_from'],
    'short reason' => [['reason' => 'قصير'], 'reason'],
]);

it('makes a path free with a zero price', function (): void {
    setServicePrices('1000.00');

    switchTo(systemAdmin())->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['price_before_vat' => '0.00']))->assertOk()
        ->assertJsonPath('data.pricing', ['price' => null, 'is_free' => true, 'availability' => 'available']);
});

it('requires a reason from the Super Admin and raises the security alert', function (): void {
    setServicePrices('1000.00');
    $superAdmin = superAdmin();

    switchTo($superAdmin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited))->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    expect(ServicePriceVersion::query()->where('purpose', 'accredited')->count())->toBe(1);

    switchTo($superAdmin)->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['reason' => PRICES_REASON]))->assertOk();

    expect(Audit::query()->where('event', AuditEvent::PricesSettingsUpdated->value)->sole()->reason)->toBe(PRICES_REASON);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('denies every role without the path prices permission, guests and entities', function (RoleKey $role): void {
    switchTo(staffUser($role))->getJson(PATH_PRICES)->assertForbidden();
    test()->putJson(PATH_PRICES.'/self', pricePayload(PaymentPurpose::SelfAssessment))->assertForbidden();

    expect(ServicePriceVersion::query()->where('purpose', 'self')->where('price_before_vat', '1200.00')->exists())->toBeFalse();
})->with(nonSystemAdminRoles());

it('refuses guests and entities and answers 404 for an unknown or retired row', function (): void {
    test()->getJson(PATH_PRICES)->assertUnauthorized();
    switchTo(entityUser())->getJson(PATH_PRICES)->assertForbidden();
    switchTo(systemAdmin())->putJson(PATH_PRICES.'/leadership', ['price_before_vat' => '1.00', 'availability' => 'available', 'base_version_id' => null])->assertNotFound();
});

it('grants the permission through the matrix to another role', function (): void {
    $officer = financeOfficer();
    systemAdmin();
    Role::findByName(RoleKey::FinanceOfficer->value)->givePermissionTo(PermissionKey::SettingsPricesManage->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    switchTo($officer)->getJson(PATH_PRICES)->assertOk();
    expect(PermissionKey::SettingsPricesManage->isGrantable())->toBeTrue()
        ->and(Role::findByName(RoleKey::SystemAdmin->value, 'web')->hasPermissionTo(PermissionKey::SettingsPricesManage->value))->toBeTrue();
});

it('never changes an order created before a price change', function (): void {
    ['owner' => $owner] = payingEntity();
    $open = openedPayment($owner);

    Date::setTestNow(Date::now()->addMinute());
    switchTo(systemAdmin())->putJson(PATH_PRICES.'/accredited', pricePayload(PaymentPurpose::Accredited, ['price_before_vat' => '3000.00']))->assertOk();

    expect($open->refresh()->only(['price_before_vat', 'total']))->toBe(['price_before_vat' => '1000.00', 'total' => '1150.00']);
    startPayment($owner)->assertOk()->assertJsonPath('meta.existing_order', true)->assertJsonPath('data.pricing.total', '1150.00');
    startPayment(ownerOf(entityWithOwner()))->assertCreated()->assertJsonPath('data.pricing.total', '3450.00');
});
