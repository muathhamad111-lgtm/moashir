<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Settings\SettingKey;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Audit\Audit;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\Role;
use App\Models\Settings\Setting;
use App\Services\Documents\DocumentHtmlComposer;
use App\Services\Finance\ReceiptDocumentBuilder;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

const VAT_NUMBER = '300000000000003';

const OTHER_VAT_NUMBER = '312345678900003';

beforeEach(function (): void {
    Notification::fake();
});

function setVatRegistrationNumber(?string $number): void
{
    app(SettingsStore::class)->put(SettingKey::VatRegistrationNumber, $number, AuditEvent::FinanceSettingsUpdated, null);
}

it('lets the System Administrator set, change and clear the platform VAT registration number with an audit', function (): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/settings/finance')->assertOk()
        ->assertJsonPath('data.vat_registration_number', null)
        ->assertJsonPath('data.vat_registration_reminder', true);
    $this->putJson('/api/v1/settings/finance', ['vat_registration_number' => VAT_NUMBER])->assertOk()
        ->assertJsonPath('data.vat_registration_number', VAT_NUMBER)
        ->assertJsonPath('data.vat_registration_reminder', false);
    $this->putJson('/api/v1/settings/finance', ['vat_registration_number' => null])->assertOk()
        ->assertJsonPath('data.vat_registration_number', null)
        ->assertJsonPath('data.vat_registration_reminder', true);

    $audits = Audit::query()->where('event', AuditEvent::FinanceSettingsUpdated->value)->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->new_values)->toBe([SettingKey::VatRegistrationNumber->value => VAT_NUMBER])
        ->and($audits[1]->old_values)->toBe([SettingKey::VatRegistrationNumber->value => VAT_NUMBER])
        ->and($audits[1]->new_values)->toBe([SettingKey::VatRegistrationNumber->value => null]);
});

it('hides the reminder when the VAT rate is zero and saves other finance fields with an empty number', function (): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/settings/finance', ['vat_rate' => '0.00'])->assertOk()
        ->assertJsonPath('data.vat_registration_reminder', false);
    $this->putJson('/api/v1/settings/finance', ['order_validity_minutes' => 20])->assertOk();
});

it('rejects a malformed VAT registration number', function (string $value): void {
    signIn(systemAdmin());

    $this->putJson('/api/v1/settings/finance', ['vat_registration_number' => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vat_registration_number');
    expect(Setting::query()->where('key', SettingKey::VatRegistrationNumber->value)->exists())->toBeFalse();
})->with([
    'too short' => ['30000000000003'],
    'too long' => ['3000000000000003'],
    'wrong first digit' => ['200000000000003'],
    'wrong last digit' => ['300000000000002'],
    'letters' => ['3000000000000A3'],
    'inner space' => ['3000000 00000003'],
    'arabic-indic digits' => ['٣٠٠٠٠٠٠٠٠٠٠٠٠٠٣'],
]);

it('keeps the field to the holders of the payment settings permission', function (): void {
    Role::findByName(RoleKey::FinanceOfficer->value)->givePermissionTo(PermissionKey::SettingsAssessmentManage->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    signIn(financeOfficer());

    $this->putJson('/api/v1/settings/finance', ['vat_registration_number' => VAT_NUMBER])->assertForbidden();
    $this->putJson('/api/v1/settings/finance', ['vat_rate' => '15.00'])->assertOk();
});

it('requires a reason from the Super Admin', function (): void {
    signIn(superAdmin());

    $this->putJson('/api/v1/settings/finance', ['vat_registration_number' => VAT_NUMBER])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    $this->putJson('/api/v1/settings/finance', ['vat_registration_number' => VAT_NUMBER, 'reason' => 'ضبط الرقم الضريبي المعتمد للمنصة بعد التسجيل.'])->assertOk();
});

it('snapshots the number into a receipt issued while it is set and shows it on the receipt and its PDF', function (): void {
    setVatRegistrationNumber(VAT_NUMBER);
    $payment = storedPaidPayment(entityWithOwner(), IntegrationMode::Live);
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();

    setVatRegistrationNumber(OTHER_VAT_NUMBER);

    switchTo(ownerOf($payment->entity))->getJson("/api/v1/payments/{$payment->public_id}/receipt")->assertOk()
        ->assertJsonPath('data.vat_registration_number', VAT_NUMBER)
        ->assertJsonPath('data.snapshot.vat_registration_number', VAT_NUMBER);

    $html = app(DocumentHtmlComposer::class)->compose(app(ReceiptDocumentBuilder::class)->for($receipt));
    $audit = Audit::query()->where('event', AuditEvent::ReceiptIssued->value)->where('auditable_id', $receipt->id)->sole();

    expect($html)->toContain(VAT_NUMBER)
        ->and($html)->toContain('الرقم الضريبي')
        ->and($html)->not->toContain(OTHER_VAT_NUMBER)
        ->and($html)->not->toContain('لا يُعد فاتورة ضريبية')
        ->and($audit->new_values['vat_registration_number_present'])->toBeTrue();
});

it('issues a receipt without the VAT row while the number is empty and never blocks the payment', function (): void {
    $payment = storedPaidPayment(entityWithOwner(), IntegrationMode::Live);
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();

    $html = app(DocumentHtmlComposer::class)->compose(app(ReceiptDocumentBuilder::class)->for($receipt));
    $audit = Audit::query()->where('event', AuditEvent::ReceiptIssued->value)->where('auditable_id', $receipt->id)->sole();

    expect($receipt->snapshot)->not->toHaveKey('vat_registration_number')
        ->and($html)->not->toContain('الرقم الضريبي')
        ->and($audit->new_values['vat_registration_number_present'])->toBeFalse();
    switchTo(ownerOf($payment->entity))->getJson("/api/v1/payments/{$payment->public_id}/receipt")->assertOk()
        ->assertJsonPath('data.vat_registration_number', null);
});

it('keeps the issue-time number when the receipt is reissued', function (): void {
    setVatRegistrationNumber(VAT_NUMBER);
    $payment = storedPaidPayment(entityWithOwner(), IntegrationMode::Live);
    $receipt = PaymentReceipt::query()->where('payment_id', $payment->id)->sole();
    setVatRegistrationNumber(null);

    switchTo(financeOfficer())->postJson("/api/v1/finance/receipts/{$receipt->public_id}/reissue", ['reason' => 'تحديث بيانات العرض في القالب المعتمد.'])->assertOk()
        ->assertJsonPath('data.vat_registration_number', VAT_NUMBER);

    $document = collect(app(FakePdfRenderer::class)->rendered())->last();
    expect($document?->data['vat_registration_number'])->toBe(VAT_NUMBER);
});
