<?php

declare(strict_types=1);

use App\Contracts\Finance\ServicePriceSource;
use App\Data\Finance\PriceQuote;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\Paths\PathRejected;
use App\Services\Finance\PriceCalculator;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\VatRate;

it('adds VAT to the price entered before VAT with half-up rounding in halalas', function (string $price, string $rate, string $vat, string $total): void {
    $quote = PriceQuote::of(new Money($price), new VatRate($rate));

    expect($quote->vatAmount->amount)->toBe($vat)
        ->and($quote->total->amount)->toBe($total)
        ->and($quote->vatRate->percent)->toBe(number_format((float) $rate, 2, '.', ''));
})->with([
    'standard rate' => ['1000.00', '15', '150.00', '1150.00'],
    'rounds half up' => ['0.10', '15', '0.02', '0.12'],
    'rounds down below half' => ['0.03', '15', '0.00', '0.03'],
    'fractional rate' => ['199.99', '15.5', '31.00', '230.99'],
    'zero rate' => ['500.00', '0', '0.00', '500.00'],
    'large price' => ['9999999.99', '15.00', '1500000.00', '11499999.99'],
]);

it('quotes each service from its price and the single VAT rate setting', function (): void {
    setServicePrices('1000.00', '2500.00');
    setIntegrationSetting(SettingKey::VatRate, '5.00');
    $calculator = app(PriceCalculator::class);

    expect($calculator->quote(PaymentPurpose::Accredited)->total->amount)->toBe('1050.00')
        ->and($calculator->quote(PaymentPurpose::Bag)->total->amount)->toBe('2625.00')
        ->and(app(ServicePriceSource::class)->priceBeforeVatOf(PaymentPurpose::Accredited)?->amount)->toBe('1000.00');
});

it('uses the default VAT rate of 15 percent', function (): void {
    setServicePrices();

    expect(app(PriceCalculator::class)->quote(PaymentPurpose::Accredited)->vatAmount->amount)->toBe('150.00');
});

it('refuses to quote a service whose price is not set: the path is paused', function (): void {
    setServicePrices(null, null);

    expect(fn () => app(PriceCalculator::class)->quote(PaymentPurpose::Accredited))->toThrow(PathRejected::class, 'path.unavailable');
});

it('refuses to quote a free service: no order is needed', function (): void {
    setServicePrices('0.00', '0.00');

    expect(fn () => app(PriceCalculator::class)->quote(PaymentPurpose::Accredited))->toThrow(PaymentRejected::class, 'payment.not_required');
});

it('validates VAT rates and money amounts', function (): void {
    expect(fn (): VatRate => new VatRate('100.01'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): VatRate => new VatRate('-1'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): VatRate => new VatRate('15.555'))->toThrow(InvalidArgumentException::class)
        ->and(new VatRate('100')->basisPoints())->toBe(10_000)
        ->and(Money::fromMinorUnits(115000)->amount)->toBe('1150.00')
        ->and(new Money('1150.00')->inMinorUnits())->toBe(115000)
        ->and(new Money('10.05')->plus(new Money('0.95'))->amount)->toBe('11.00')
        ->and(fn (): Money => new Money('1.00')->plus(new Money('1.00', 'USD')))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Money => Money::fromMinorUnits(-1))->toThrow(InvalidArgumentException::class);
});
