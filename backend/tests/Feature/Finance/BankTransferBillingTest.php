<?php

declare(strict_types=1);

use App\Enums\Entities\ProfileSection;
use App\Models\Entities\Entity;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('shows Finance the payer billing data and flags it when it is incomplete', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    $entity = Entity::query()->findOrFail($owner->entity_id);

    saveAndApproveSection($entity, ProfileSection::Billing, ['vat_registered' => true, 'vat_number' => '310123456700003'])->assertOk();

    switchTo(transferReviewer())->getJson(bankTransferUrl($payment))->assertOk()
        ->assertJsonPath('data.billing.address_building_number', '7421')
        ->assertJsonPath('data.billing.address_city', 'الرياض')
        ->assertJsonPath('data.billing.vat_registered', true)
        ->assertJsonPath('data.billing.vat_number', '310123456700003')
        ->assertJsonPath('data.billing.vat_certificate', null)
        ->assertJsonPath('data.billing_complete', false);

    switchTo(transferReviewer())->postJson(bankTransferUrl($payment, 'vat-certificate/download'))
        ->assertNotFound()
        ->assertJsonPath('code', 'entity.vat_certificate_missing');
});

it('reports complete billing data and lets Finance download the certificate', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);
    $entity = Entity::query()->findOrFail($owner->entity_id);
    saveAndApproveSection($entity, ProfileSection::Billing, ['vat_registered' => true, 'vat_number' => '310123456700003'])->assertOk();
    uploadAndApproveVatCertificate($owner, transferReceipt('vat-certificate.pdf'))->assertOk();

    $reviewer = transferReviewer();
    switchTo($reviewer)->getJson(bankTransferUrl($payment))->assertOk()
        ->assertJsonPath('data.billing.vat_certificate.name', 'vat-certificate.pdf')
        ->assertJsonPath('data.billing_complete', true);

    $url = (string) switchTo($reviewer)->postJson(bankTransferUrl($payment, 'vat-certificate/download'))->assertOk()->json('data.url');
    switchTo($reviewer)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('keeps the certificate download away from staff outside Finance', function (): void {
    ['owner' => $owner, 'account' => $account] = transferPayingEntity();
    $payment = submittedTransfer($owner, $account);

    switchTo(contentOfficer())->postJson(bankTransferUrl($payment, 'vat-certificate/download'))->assertForbidden();
    switchTo($owner)->postJson(bankTransferUrl($payment, 'vat-certificate/download'))->assertForbidden();
});
