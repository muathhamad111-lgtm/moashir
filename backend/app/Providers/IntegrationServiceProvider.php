<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BreachedPasswordChecker;
use App\Contracts\Documents\PdfRenderer;
use App\Contracts\Files\ClamdConnector;
use App\Contracts\Files\FileScanner;
use App\Contracts\Integrations\HostResolver;
use App\Contracts\Integrations\PaymentGateway;
use App\Contracts\Integrations\PaymentWebhookRegistrar;
use App\Contracts\Integrations\PaymentWebhookVerifier;
use App\Contracts\Integrations\SmsAvailability;
use App\Contracts\Integrations\SmtpTransportFactory;
use App\Contracts\SmsGateway;
use App\Data\Integrations\SmsDriverSelection;
use App\Integrations\Anthropic\AnthropicConnectionTester;
use App\Integrations\Anthropic\AnthropicProvider;
use App\Integrations\ClamAv\ClamAvFileScanner;
use App\Integrations\ClamAv\SocketClamdConnector;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Integrations\Fakes\FakeFileScanner;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Integrations\Fakes\FakeSmsGateway;
use App\Integrations\Moyasar\MoyasarConnectionTester;
use App\Integrations\Moyasar\MoyasarPaymentGateway;
use App\Integrations\Moyasar\MoyasarWebhookRegistrar;
use App\Integrations\Moyasar\MoyasarWebhookVerifier;
use App\Integrations\Msegat\MsegatAccount;
use App\Integrations\Msegat\MsegatConnectionTester;
use App\Integrations\Msegat\MsegatSmsGateway;
use App\Integrations\OpenAi\OpenAiConnectionTester;
use App\Integrations\OpenAi\OpenAiProvider;
use App\Integrations\Smtp\SettingsMailTransport;
use App\Integrations\Smtp\SmtpConnectionTester;
use App\Integrations\Smtp\SmtpTransportBuilder;
use App\Integrations\Smtp\SystemHostResolver;
use App\Integrations\Taqnyat\TaqnyatAccount;
use App\Integrations\Taqnyat\TaqnyatConnectionTester;
use App\Integrations\Taqnyat\TaqnyatSmsGateway;
use App\Services\Identity\LocalBreachedPasswordChecker;
use App\Services\Integrations\ConfiguredSmsAvailability;
use App\Services\Integrations\ProviderAdapterRegistry;
use App\Services\Integrations\SettingsSmsGateway;
use App\Services\Platform\LocalFakeAdapters;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

final class IntegrationServiceProvider extends ServiceProvider
{
    private const array ADAPTERS = [
        TaqnyatSmsGateway::class,
        TaqnyatAccount::class,
        TaqnyatConnectionTester::class,
        MsegatSmsGateway::class,
        MsegatAccount::class,
        MsegatConnectionTester::class,
        MoyasarConnectionTester::class,
        SmtpConnectionTester::class,
        OpenAiProvider::class,
        OpenAiConnectionTester::class,
        AnthropicProvider::class,
        AnthropicConnectionTester::class,
    ];

    public function register(): void
    {
        $this->app->singleton(SmsDriverSelection::class, fn (): SmsDriverSelection => new SmsDriverSelection(
            $this->app->make(LocalFakeAdapters::class)->allows('moashir.sms.driver'),
        ));
        $this->app->singleton(SmsGateway::class, fn (): SmsGateway => $this->app->make(SmsDriverSelection::class)->usesFakeGateway
            ? $this->app->make(FakeSmsGateway::class)
            : $this->app->make(SettingsSmsGateway::class));
        $this->app->singleton(BreachedPasswordChecker::class, LocalBreachedPasswordChecker::class);
        $this->app->bind(SmsAvailability::class, ConfiguredSmsAvailability::class);
        $this->app->bind(PaymentGateway::class, MoyasarPaymentGateway::class);
        $this->app->bind(PaymentWebhookVerifier::class, MoyasarWebhookVerifier::class);
        $this->app->bind(PaymentWebhookRegistrar::class, MoyasarWebhookRegistrar::class);
        $this->app->bind(SmtpTransportFactory::class, SmtpTransportBuilder::class);
        $this->app->bind(HostResolver::class, SystemHostResolver::class);
        $this->app->bind(ClamdConnector::class, SocketClamdConnector::class);
        $this->app->singleton(FakeFileScanner::class);
        $this->app->singleton(FakePdfRenderer::class);
        $this->app->singleton(FileScanner::class, fn (): FileScanner => $this->app->make(LocalFakeAdapters::class)->allows('moashir.files.scanner')
            ? $this->app->make(FakeFileScanner::class)
            : $this->app->make(ClamAvFileScanner::class));
        $this->app->singleton(PdfRenderer::class, fn (): PdfRenderer => $this->app->make(LocalFakeAdapters::class)->allows('moashir.documents.pdf_renderer')
            ? $this->app->make(FakePdfRenderer::class)
            : $this->app->make(UnavailablePdfRenderer::class));
        $this->app->tag(self::ADAPTERS, ProviderAdapterRegistry::TAG);
    }

    public function boot(): void
    {
        Mail::extend('settings', fn (): SettingsMailTransport => $this->app->make(SettingsMailTransport::class));
    }
}
