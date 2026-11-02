<?php

declare(strict_types=1);

use App\Contracts\Documents\PdfRenderer;
use App\Contracts\Files\FileScanner;
use App\Contracts\Integrations\SmsAvailability;
use App\Contracts\SmsGateway;
use App\Data\Integrations\SmsDriverSelection;
use App\Integrations\ClamAv\ClamAvFileScanner;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Integrations\Fakes\FakeFileScanner;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Integrations\Fakes\FakeSmsGateway;
use App\Services\Integrations\SettingsSmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * @return array{0: FileScanner, 1: PdfRenderer}
 */
function resolveFreshAdapters(): array
{
    app()->forgetInstance(FileScanner::class);
    app()->forgetInstance(PdfRenderer::class);

    return [app(FileScanner::class), app(PdfRenderer::class)];
}

it('uses the fake scanner and PDF renderer when the local flags ask for them in the test environment', function (): void {
    [$scanner, $renderer] = resolveFreshAdapters();

    expect($scanner)->toBeInstanceOf(FakeFileScanner::class)
        ->and($renderer)->toBeInstanceOf(FakePdfRenderer::class);
});

it('uses the fakes in local development', function (): void {
    app()['env'] = 'local';

    [$scanner, $renderer] = resolveFreshAdapters();

    expect($scanner)->toBeInstanceOf(FakeFileScanner::class)
        ->and($renderer)->toBeInstanceOf(FakePdfRenderer::class);
});

it('refuses the fake flags outside local and testing and logs the refusal', function (string $environment): void {
    Log::spy();
    app()['env'] = $environment;

    [$scanner, $renderer] = resolveFreshAdapters();

    expect($scanner)->toBeInstanceOf(ClamAvFileScanner::class)
        ->and($renderer)->toBeInstanceOf(UnavailablePdfRenderer::class);
    Log::shouldHaveReceived('critical')->with('platform.fake_adapter_refused', ['setting' => 'moashir.files.scanner', 'environment' => $environment])->once();
    Log::shouldHaveReceived('critical')->with('platform.fake_adapter_refused', ['setting' => 'moashir.documents.pdf_renderer', 'environment' => $environment])->once();
})->with(['staging', 'production']);

it('uses the real adapters when the flags are not set', function (): void {
    config()->set('moashir.files.scanner', 'clamav');
    config()->set('moashir.documents.pdf_renderer', 'unavailable');

    [$scanner, $renderer] = resolveFreshAdapters();

    expect($scanner)->toBeInstanceOf(ClamAvFileScanner::class)
        ->and($renderer)->toBeInstanceOf(UnavailablePdfRenderer::class);
});

/**
 * @return array{0: SmsGateway, 1: SmsAvailability}
 */
function resolveFreshSmsAdapters(): array
{
    app()->forgetInstance(SmsDriverSelection::class);
    app()->forgetInstance(SmsGateway::class);

    return [app(SmsGateway::class), app(SmsAvailability::class)];
}

it('uses the in-memory SMS gateway when SMS_PROVIDER asks for the fake in local and testing', function (string $environment): void {
    app()['env'] = $environment;

    [$gateway, $availability] = resolveFreshSmsAdapters();

    expect($gateway)->toBeInstanceOf(FakeSmsGateway::class)
        ->and($availability->isAvailable())->toBeTrue();
})->with(['testing', 'local']);

it('refuses the fake SMS gateway outside local and testing, uses the settings gateway and logs the refusal once', function (string $environment): void {
    Log::spy();
    app()['env'] = $environment;

    [$gateway, $availability] = resolveFreshSmsAdapters();

    expect($gateway)->toBeInstanceOf(SettingsSmsGateway::class)
        ->and($availability->isAvailable())->toBeFalse()
        ->and(app(SmsAvailability::class)->isAvailable())->toBeFalse();
    Log::shouldHaveReceived('critical')->with('platform.fake_adapter_refused', ['setting' => 'moashir.sms.driver', 'environment' => $environment])->once();
})->with(['staging', 'production']);

it('uses the settings gateway when SMS_PROVIDER does not ask for the fake', function (): void {
    Log::spy();
    config()->set('moashir.sms.driver', 'settings');

    [$gateway, $availability] = resolveFreshSmsAdapters();

    expect($gateway)->toBeInstanceOf(SettingsSmsGateway::class)
        ->and($availability->isAvailable())->toBeFalse();
    Log::shouldNotHaveReceived('critical');
});
