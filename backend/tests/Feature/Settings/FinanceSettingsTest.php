<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Models\Finance\Payment;
use App\Models\Identity\Role;
use App\Models\Settings\Setting;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

const FINANCE_SETTINGS = '/api/v1/settings/finance';

const FINANCE_SETTINGS_REASON = 'تحديث الأسعار المعتمدة لبيئة الاختبار وفق قرار اللجنة المالية.';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function auditedSettingChange(SettingKey $key): array
{
    $audit = Audit::query()
        ->where('event', AuditEvent::FinanceSettingsUpdated->value)
        ->where('auditable_id', Setting::query()->where('key', $key->value)->value('id'))
        ->sole();

    return ['old' => $audit->old_values, 'new' => $audit->new_values, 'reason' => $audit->reason, 'user_id' => $audit->user_id];
}

function grantRole(RoleKey $role, PermissionKey $permission): void
{
    Role::findByName($role->value)->givePermissionTo($permission->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('shows the defaults to the System Administrator: no prices yet, VAT 15.00 and a 30-minute order validity', function (): void {
    signIn(systemAdmin());

    $this->getJson(FINANCE_SETTINGS)
        ->assertOk()
        ->assertExactJson(['data' => [
            'currency' => 'SAR',
            'accredited_price' => null,
            'bag_price' => null,
            'vat_rate' => '15.00',
            'order_validity_minutes' => 30,
            'order_validity_limits' => ['min' => 5, 'max' => 60],
            'vat_registration_number' => null,
            'vat_registration_reminder' => true,
            'pricing' => ['accredited' => null, 'bag' => null],
            'editable_fields' => ['accredited_price', 'bag_price', 'vat_rate', 'order_validity_minutes', 'vat_registration_number'],
        ], 'meta' => [], 'errors' => null]);
});

it('shows the configured prices before VAT with the server-computed VAT and total', function (): void {
    setServicePrices('1000.00', '2499.99');
    setIntegrationSetting(SettingKey::VatRate, '15.00');
    signIn(systemAdmin());

    $this->getJson(FINANCE_SETTINGS)
        ->assertOk()
        ->assertJsonPath('data.accredited_price', '1000.00')
        ->assertJsonPath('data.bag_price', '2499.99')
        ->assertJsonPath('data.pricing.accredited', ['price_before_vat' => '1000.00', 'vat_rate' => '15.00', 'vat_amount' => '150.00', 'total' => '1150.00', 'currency' => 'SAR'])
        ->assertJsonPath('data.pricing.bag', ['price_before_vat' => '2499.99', 'vat_rate' => '15.00', 'vat_amount' => '375.00', 'total' => '2874.99', 'currency' => 'SAR']);
});

it('updates every finance setting, audits each change from the old to the new value and answers with the full shape', function (): void {
    $admin = systemAdmin();
    signIn($admin);

    $this->putJson(FINANCE_SETTINGS, [
        'accredited_price' => '1200.00',
        'bag_price' => '2500.00',
        'vat_rate' => '5',
        'order_validity_minutes' => 45,
    ])
        ->assertOk()
        ->assertJsonPath('data.accredited_price', '1200.00')
        ->assertJsonPath('data.bag_price', '2500.00')
        ->assertJsonPath('data.vat_rate', '5.00')
        ->assertJsonPath('data.order_validity_minutes', 45)
        ->assertJsonPath('data.pricing.accredited.vat_amount', '60.00')
        ->assertJsonPath('data.pricing.accredited.total', '1260.00')
        ->assertJsonPath('data.pricing.bag.total', '2625.00');

    expect(auditedSettingChange(SettingKey::AccreditedPrice))->toBe(['old' => ['assessment.accredited_price' => null], 'new' => ['assessment.accredited_price' => '1200.00'], 'reason' => null, 'user_id' => $admin->id])
        ->and(auditedSettingChange(SettingKey::BagPrice))->toMatchArray(['old' => ['bag.price' => null], 'new' => ['bag.price' => '2500.00']])
        ->and(auditedSettingChange(SettingKey::VatRate))->toMatchArray(['old' => ['assessment.vat_rate' => '15.00'], 'new' => ['assessment.vat_rate' => '5.00']])
        ->and(auditedSettingChange(SettingKey::PaymentOrderValidityMinutes))->toMatchArray(['old' => ['payment.order_validity_minutes' => 30], 'new' => ['payment.order_validity_minutes' => 45]]);

    Notification::assertNothingSent();
});

it('keeps omitted settings, writes no audit for an unchanged value and records an optional System Administrator reason', function (): void {
    setServicePrices('1000.00', '2500.00');
    signIn(systemAdmin());

    $this->putJson(FINANCE_SETTINGS, ['accredited_price' => '1000.00', 'reason' => FINANCE_SETTINGS_REASON])->assertOk();
    expect(Audit::query()->where('event', AuditEvent::FinanceSettingsUpdated->value)->count())->toBe(0);

    $this->putJson(FINANCE_SETTINGS, ['bag_price' => '2750.50', 'reason' => FINANCE_SETTINGS_REASON])
        ->assertOk()
        ->assertJsonPath('data.accredited_price', '1000.00')
        ->assertJsonPath('data.bag_price', '2750.50')
        ->assertJsonPath('data.vat_rate', '15.00')
        ->assertJsonPath('data.order_validity_minutes', 30);

    expect(auditedSettingChange(SettingKey::BagPrice))->toMatchArray(['old' => ['bag.price' => '2500.00'], 'new' => ['bag.price' => '2750.50'], 'reason' => FINANCE_SETTINGS_REASON])
        ->and(Audit::query()->where('event', AuditEvent::FinanceSettingsUpdated->value)->count())->toBe(1);
});

it('treats an empty update as a no-op', function (): void {
    signIn(systemAdmin());

    $this->putJson(FINANCE_SETTINGS, [])->assertOk()->assertJsonPath('data.vat_rate', '15.00');

    expect(Setting::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::FinanceSettingsUpdated->value)->exists())->toBeFalse();
});

it('requires a reason from the Super Admin, changes nothing without it and alerts on the reasoned change', function (): void {
    $superAdmin = superAdmin();
    signIn($superAdmin);

    $this->putJson(FINANCE_SETTINGS, ['vat_rate' => '10.00'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required')
        ->assertJsonPath('errors.reason.0', __('errors.reason_required', [], 'ar'));
    expect(Setting::query()->where('key', SettingKey::VatRate->value)->exists())->toBeFalse();
    Notification::assertNothingSent();

    $this->putJson(FINANCE_SETTINGS, ['vat_rate' => '10.00', 'reason' => FINANCE_SETTINGS_REASON])
        ->assertOk()
        ->assertJsonPath('data.vat_rate', '10.00')
        ->assertJsonPath('data.editable_fields', ['accredited_price', 'bag_price', 'vat_rate', 'order_validity_minutes', 'vat_registration_number']);

    expect(auditedSettingChange(SettingKey::VatRate))->toMatchArray(['reason' => FINANCE_SETTINGS_REASON, 'user_id' => $superAdmin->id]);
    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
});

it('lets the Super Admin read the finance settings', function (): void {
    signIn(superAdmin());

    $this->getJson(FINANCE_SETTINGS)->assertOk()->assertJsonPath('data.vat_rate', '15.00');
});

it('denies every staff role without the finance setting permissions', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(FINANCE_SETTINGS)->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->putJson(FINANCE_SETTINGS, ['accredited_price' => '900.00'])->assertForbidden()->assertJsonPath('code', 'forbidden');

    expect(Setting::query()->count())->toBe(0);
})->with(nonSystemAdminRoles());

it('refuses guests and entities', function (): void {
    $this->getJson(FINANCE_SETTINGS)->assertUnauthorized();
    $this->putJson(FINANCE_SETTINGS, ['accredited_price' => '900.00'])->assertUnauthorized();

    signIn(entityUser());
    $this->getJson(FINANCE_SETTINGS)->assertForbidden();
    $this->putJson(FINANCE_SETTINGS, ['accredited_price' => '900.00'])->assertForbidden();
});

it('guards the Kit price with its own permission', function (): void {
    grantRole(RoleKey::FinanceOfficer, PermissionKey::SettingsAssessmentManage);
    signIn(financeOfficer());

    $this->getJson(FINANCE_SETTINGS)
        ->assertOk()
        ->assertJsonPath('data.editable_fields', ['accredited_price', 'vat_rate', 'order_validity_minutes']);
    $this->putJson(FINANCE_SETTINGS, ['accredited_price' => '900.00', 'bag_price' => '100.00'])->assertForbidden();
    expect(Setting::query()->count())->toBe(0);

    $this->putJson(FINANCE_SETTINGS, ['accredited_price' => '900.00'])->assertOk()->assertJsonPath('data.accredited_price', '900.00');
});

it('lets a holder of the Kit permission alone change only the Kit price', function (): void {
    grantRole(RoleKey::ContentOfficer, PermissionKey::BagAdminManage);
    signIn(staffUser(RoleKey::ContentOfficer));

    $this->getJson(FINANCE_SETTINGS)->assertOk()->assertJsonPath('data.editable_fields', ['bag_price']);
    $this->putJson(FINANCE_SETTINGS, ['vat_rate' => '10.00'])->assertForbidden();
    $this->putJson(FINANCE_SETTINGS, ['bag_price' => '1999.00'])->assertOk()->assertJsonPath('data.bag_price', '1999.00');
});

it('rejects invalid finance settings without changing anything', function (array $payload, string $field): void {
    signIn(systemAdmin());

    $this->putJson(FINANCE_SETTINGS, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);

    expect(Setting::query()->count())->toBe(0);
})->with([
    'price without decimals' => [['accredited_price' => '1000'], 'accredited_price'],
    'price with three decimals' => [['accredited_price' => '1000.005'], 'accredited_price'],
    'price as a JSON number' => [['accredited_price' => 1000.5], 'accredited_price'],
    'negative price' => [['bag_price' => '-10.00'], 'bag_price'],
    'zero price' => [['bag_price' => '0.00'], 'bag_price'],
    'price above the column range' => [['bag_price' => '10000000.00'], 'bag_price'],
    'empty price' => [['accredited_price' => ''], 'accredited_price'],
    'null price' => [['accredited_price' => null], 'accredited_price'],
    'Eastern Arabic digits' => [['accredited_price' => '١٠٠٠.٠٠'], 'accredited_price'],
    'VAT above 100 percent' => [['vat_rate' => '100.01'], 'vat_rate'],
    'VAT with three decimals' => [['vat_rate' => '15.125'], 'vat_rate'],
    'negative VAT' => [['vat_rate' => '-5.00'], 'vat_rate'],
    'VAT as a JSON number' => [['vat_rate' => 15], 'vat_rate'],
    'VAT as text' => [['vat_rate' => 'fifteen'], 'vat_rate'],
    'order validity below 5 minutes' => [['order_validity_minutes' => 4], 'order_validity_minutes'],
    'order validity above 60 minutes' => [['order_validity_minutes' => 61], 'order_validity_minutes'],
    'order validity with decimals' => [['order_validity_minutes' => 12.5], 'order_validity_minutes'],
    'short reason' => [['vat_rate' => '15.00', 'reason' => 'قصير'], 'reason'],
]);

it('returns translated field messages for an invalid price', function (): void {
    signIn(systemAdmin());

    $this->putJson(FINANCE_SETTINGS, ['accredited_price' => '0.00'], ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.accredited_price.0', 'The accredited assessment price before VAT must be greater than zero.');

    $this->putJson(FINANCE_SETTINGS, ['vat_rate' => '120'], ['Accept-Language' => 'ar'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.vat_rate.0', 'يجب أن تكون نسبة ضريبة القيمة المضافة نسبة مئوية بين 0 و100 بخانتين عشريتين على الأكثر، مثل 15.00.');
});

it('accepts a zero VAT rate and the order validity bounds', function (): void {
    setServicePrices('1000.00', '2500.00');
    signIn(systemAdmin());

    $this->putJson(FINANCE_SETTINGS, ['vat_rate' => '0.00', 'order_validity_minutes' => 5])
        ->assertOk()
        ->assertJsonPath('data.pricing.accredited.total', '1000.00')
        ->assertJsonPath('data.order_validity_minutes', 5);
    $this->putJson(FINANCE_SETTINGS, ['vat_rate' => '100', 'order_validity_minutes' => '60'])
        ->assertOk()
        ->assertJsonPath('data.vat_rate', '100.00')
        ->assertJsonPath('data.order_validity_minutes', 60);
});

it('never changes existing payments and prices new orders with the new settings', function (): void {
    ['owner' => $owner] = payingEntity();
    $open = openedPayment($owner);
    $openSnapshot = $open->only(['price_before_vat', 'vat_rate', 'vat_amount', 'total', 'expires_at']);
    $paid = storedPaidPayment();
    $paidSnapshot = $paid->only(['price_before_vat', 'vat_rate', 'vat_amount', 'total']);

    Date::setTestNow(Date::now()->addMinute()->startOfSecond());
    switchTo(systemAdmin())->putJson(FINANCE_SETTINGS, [
        'accredited_price' => '2000.00',
        'vat_rate' => '5.00',
        'order_validity_minutes' => 10,
    ])->assertOk();

    expect($open->refresh()->only(['price_before_vat', 'vat_rate', 'vat_amount', 'total', 'expires_at']))->toEqual($openSnapshot)
        ->and($paid->refresh()->only(['price_before_vat', 'vat_rate', 'vat_amount', 'total']))->toBe($paidSnapshot);

    startPayment($owner)->assertOk()->assertJsonPath('meta.existing_order', true)->assertJsonPath('data.pricing.total', ACCREDITED_TOTAL);

    $newcomer = ownerOf(entityWithOwner());
    $created = startPayment($newcomer)->assertCreated()
        ->assertJsonPath('data.pricing', ['price_before_vat' => '2000.00', 'vat_rate' => '5.00', 'vat_amount' => '100.00', 'total' => '2100.00', 'currency' => 'SAR']);

    $payment = Payment::query()->where('public_id', $created->json('data.id'))->sole();
    expect($payment->expires_at->toIso8601String())->toBe(Date::now()->addMinutes(10)->toIso8601String());
});

it('opens a Kit payment once the Kit price is set through the settings', function (): void {
    ['owner' => $owner] = payingEntity();
    setIntegrationSetting(SettingKey::BagPrice, null);

    startPayment($owner, 'bag')->assertConflict()->assertJsonPath('code', 'payment.price_not_configured');

    switchTo(systemAdmin())->putJson(FINANCE_SETTINGS, ['bag_price' => '3000.00'])->assertOk();

    startPayment($owner, 'bag')->assertCreated()->assertJsonPath('data.pricing.total', '3450.00');
});
