<?php

declare(strict_types=1);

use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\CredentialSource;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Integrations\Smtp\SmtpTransportBuilder;
use App\Services\Integrations\IntegrationCredentialsResolver;
use App\Services\Integrations\SmtpHostGuard;
use App\Support\Integrations\EgressAllowList;
use App\Support\Integrations\IpAddressPolicy;
use App\Support\Integrations\SmsTemplateMatcher;
use App\Support\Monitoring\SentryEventScrubber;
use App\Support\ValueObjects\SecretValue;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

it('never serializes or dumps a secret', function (): void {
    $secret = new SecretValue(FAKE_OPENAI_KEY);
    $credentials = new ResolvedCredentials(IntegrationKind::Ai, IntegrationProvider::OpenAi, IntegrationMode::Live, CredentialSource::Settings, ['model' => 'gpt-4.1'], ['api_key' => $secret]);

    expect(fn (): string => serialize($secret))->toThrow(LogicException::class)
        ->and(fn (): string => serialize($credentials))->toThrow(LogicException::class)
        ->and(print_r($secret, true))->not->toContain(FAKE_OPENAI_KEY)
        ->and(print_r($credentials, true))->not->toContain(FAKE_OPENAI_KEY)
        ->and(print_r($credentials, true))->toContain('api_key')
        ->and(json_encode($credentials))->not->toContain(FAKE_OPENAI_KEY)
        ->and($secret->last4())->toBe('0001');
});

it('allows outbound calls only to the code-defined provider hosts', function (string $host, bool $allowed): void {
    $check = fn () => EgressAllowList::assertAllowed($host);

    $allowed ? expect($check)->not->toThrow(InvalidArgumentException::class) : expect($check)->toThrow(InvalidArgumentException::class);
})->with([
    ['api.moyasar.com', true], ['api.taqnyat.sa', true], ['www.msegat.com', true], ['api.openai.com', true],
    ['eu.api.openai.com', true], ['api.anthropic.com', true], ['generativelanguage.googleapis.com', false],
    ['169.254.169.254', false], ['localhost', false], ['api.moyasar.com.attacker.example', false],
]);

it('classifies public and internal addresses', function (string $address, bool $public): void {
    expect(IpAddressPolicy::isPublic($address))->toBe($public);
})->with([
    ['62.149.100.10', true], ['2001:4860:4860::8888', true], ['10.1.2.3', false], ['172.31.255.255', false],
    ['192.168.0.1', false], ['127.0.0.1', false], ['169.254.169.254', false], ['100.64.0.1', false], ['100.127.255.255', false],
    ['100.128.0.1', true], ['0.0.0.0', false], ['224.0.0.1', false], ['::1', false], ['fc00::1', false], ['fd12:3456::1', false],
    ['fe80::1', false], ['::ffff:127.0.0.1', false], ['not-an-address', false],
]);

it('accepts only the standard SMTP ports', function (int $port, bool $allowed): void {
    expect(app(SmtpHostGuard::class)->isAllowedPort($port))->toBe($allowed);
})->with([[25, true], [465, true], [587, true], [2465, true], [2525, true], [2587, true], [22, false], [6379, false], [8025, false]]);

it('connects to the vetted address while verifying the certificate of the host name', function (): void {
    resolveHosts(['smtp.moashir.example.sa' => ['62.149.100.10']]);
    configureSmtp();
    $credentials = app(IntegrationCredentialsResolver::class)->forProvider(IntegrationProvider::Smtp);

    $transport = app(SmtpTransportBuilder::class)->build($credentials);
    $stream = $transport->getStream();

    expect($stream)->toBeInstanceOf(SocketStream::class)
        ->and($stream->getHost())->toBe('62.149.100.10')
        ->and($stream->getPort())->toBe(587)
        ->and($stream->isTLS())->toBeFalse()
        ->and($stream->getStreamOptions()['ssl']['peer_name'] ?? null)->toBe('smtp.moashir.example.sa')
        ->and($transport->isTlsRequired())->toBeTrue()
        ->and($transport->getUsername())->toBe('notifications@moashir.example.sa');
});

it('refuses to connect when the host now resolves to an internal address', function (): void {
    configureSmtp();
    resolveHosts(['smtp.moashir.example.sa' => ['62.149.100.10', '10.0.0.7']]);
    $credentials = app(IntegrationCredentialsResolver::class)->forProvider(IntegrationProvider::Smtp);

    expect(fn () => app(SmtpTransportBuilder::class)->build($credentials))
        ->toThrow(static fn (ProviderCallFailed $failure): bool => $failure->kind === ProviderErrorKind::Misconfigured);
});

it('matches SMS texts with approved provider templates regardless of placeholder style and spacing', function (string $text, string $approved, bool $matches): void {
    expect(SmsTemplateMatcher::matchesAny($text, [$approved]))->toBe($matches);
})->with([
    ['رمز الدخول إلى مؤشر: {code}', 'رمز الدخول إلى مؤشر: {{1}}', true],
    ['Moashir code:  {code}', 'Moashir code: xxxx', true],
    ['Moashir code: {code}', 'Moashir code: [code]', true],
    ['Moashir code: {code} valid {minutes} min', 'Moashir code: {{1}} valid {{2}} min', true],
    ['Moashir code: {code}', 'Your Moashir code: {{1}}', false],
]);

it('scrubs provider credentials from monitoring text', function (string $text, string $leak): void {
    expect(SentryEventScrubber::scrubText($text))->not->toContain($leak);
})->with([
    ['{"userName":"moashir-account","apiKey":"'.FAKE_MSEGAT_KEY.'"}', FAKE_MSEGAT_KEY],
    ['Authorization: Bearer '.FAKE_TAQNYAT_TOKEN, FAKE_TAQNYAT_TOKEN],
    ['x-api-key: '.FAKE_ANTHROPIC_KEY, FAKE_ANTHROPIC_KEY],
    ['failed with '.sprintf(FAKE_MOYASAR_SECRET, 'live'), sprintf(FAKE_MOYASAR_SECRET, 'live')],
    ['secret_token='.FAKE_WEBHOOK_SECRET.'&live=true', FAKE_WEBHOOK_SECRET],
    ['userName=moashir-account&apiKey='.FAKE_MSEGAT_KEY, 'moashir-account'],
]);
