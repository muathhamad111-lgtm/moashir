<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;

const SERVICE_PRICES = '/api/v1/content/prices';

it('shows the current accredited and Quick Excellence Kit prices to a guest with VAT computed by the server', function (): void {
    setServicePrices();

    $this->getJson(SERVICE_PRICES)
        ->assertOk()
        ->assertExactJson(['data' => [
            'accredited' => ['price_before_vat' => ACCREDITED_PRICE, 'vat_rate' => '15.00', 'vat_amount' => '150.00', 'total' => ACCREDITED_TOTAL, 'currency' => 'SAR'],
            'bag' => ['price_before_vat' => BAG_PRICE, 'vat_rate' => '15.00', 'vat_amount' => '375.00', 'total' => BAG_TOTAL, 'currency' => 'SAR'],
        ], 'meta' => [], 'errors' => null]);
});

it('shows the same prices to a signed-in entity and to staff', function (): void {
    setServicePrices();

    switchTo(ownerOf(entityWithOwner()))->getJson(SERVICE_PRICES)->assertOk()->assertJsonPath('data.accredited.total', ACCREDITED_TOTAL);
    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson(SERVICE_PRICES)->assertOk()->assertJsonPath('data.bag.total', BAG_TOTAL);
});

it('answers null for a service whose price is not set and follows a VAT rate change at once', function (): void {
    setServicePrices('1234.56', null);
    setIntegrationSetting(SettingKey::VatRate, '5.00');

    $this->getJson(SERVICE_PRICES)
        ->assertOk()
        ->assertJsonPath('data.bag', null)
        ->assertJsonPath('data.accredited', ['price_before_vat' => '1234.56', 'vat_rate' => '5.00', 'vat_amount' => '61.73', 'total' => '1296.29', 'currency' => 'SAR']);
});

it('answers null for both services before any price is set and never exposes other settings', function (): void {
    $response = $this->getJson(SERVICE_PRICES)->assertOk()->assertExactJson(['data' => ['accredited' => null, 'bag' => null], 'meta' => [], 'errors' => null]);

    expect(array_keys($response->json('data')))->toBe(['accredited', 'bag']);
});

it('accepts reads only and requires a JSON request', function (): void {
    $this->postJson(SERVICE_PRICES)->assertStatus(405);
    $this->get(SERVICE_PRICES, ['Accept' => 'text/html'])->assertStatus(406);
});
