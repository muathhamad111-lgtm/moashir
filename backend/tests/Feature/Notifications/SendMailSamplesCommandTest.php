<?php

declare(strict_types=1);

use App\Contracts\Integrations\SmtpTransportFactory;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Services\Notifications\MailSamples;
use Symfony\Component\Mime\Email;
use Tests\Support\RecordingSmtpTransportFactory;

const SAMPLE_INBOX = 'qa.reviewer@moashir.example.sa';

const SECOND_SAMPLE_INBOX = 'design.check@inbox.example.com';

function sampleSmtp(?string $failure = null): RecordingSmtpTransportFactory
{
    $factory = new RecordingSmtpTransportFactory($failure);
    app()->instance(SmtpTransportFactory::class, $factory);
    resolveHosts(['smtp.moashir.example.sa' => ['62.149.100.10']]);

    return $factory;
}

function inEnvironment(string $environment): void
{
    app()->instance('env', $environment);
}

/**
 * @return list<Email>
 */
function sentSamples(RecordingSmtpTransportFactory $factory): array
{
    return array_values(array_filter($factory->sent, static fn (object $message): bool => $message instanceof Email));
}

function sampleAudit(): ?Audit
{
    return Audit::query()->where('event', AuditEvent::MailSamplesSent->value)->first();
}

it('sends each main sample in both languages to every test inbox as its own message', function (): void {
    inEnvironment('staging');
    $factory = sampleSmtp();
    $connection = configureSmtp(['is_active' => true]);

    $this->artisan('moashir:mail:send-samples', ['--to' => [SAMPLE_INBOX, SECOND_SAMPLE_INBOX]])
        ->expectsOutputToContain('were sent to 2 inbox(es)')
        ->assertSuccessful();

    $sent = sentSamples($factory);
    $audit = sampleAudit();

    expect($sent)->toHaveCount(count(MailSamples::MAIN) * 2 * 2);

    foreach ($sent as $email) {
        expect($email->getTo())->toHaveCount(1)
            ->and($email->getCc())->toBe([])
            ->and($email->getHeaders()->has('Message-ID'))->toBeFalse()
            ->and((string) $email->getHtmlBody())->toContain('cid:')
            ->and((string) $email->getTextBody())->not->toBe('');
    }

    expect(array_count_values(array_map(static fn (Email $email): string => $email->getTo()[0]->getAddress(), $sent)))
        ->toBe([SAMPLE_INBOX => 16, SECOND_SAMPLE_INBOX => 16])
        ->and(array_values(array_unique(array_map(static fn (Email $email): string => $email->getFrom()[0]->getName(), $sent))))->toEqualCanonicalizing(['منصة مؤشر', 'Moashir Platform'])
        ->and($audit?->auditable_id)->toBe($connection->id)
        ->and($audit?->auditable_type)->toBe($connection->getMorphClass())
        ->and($audit?->new_values['sent'] ?? null)->toBe(32)
        ->and($audit?->new_values['ok'] ?? null)->toBeTrue()
        ->and($audit?->new_values['recipient_domains'] ?? null)->toBe(['moashir.example.sa', 'inbox.example.com'])
        ->and($audit?->new_values['samples'] ?? [])->toContain('ACC-09.assessments.accredited.entity.ar')
        ->and((string) json_encode($audit?->new_values))->not->toContain('qa.reviewer')
        ->and((string) json_encode($audit?->new_values))->not->toContain('design.check');
});

it('sends every variant of one code in the requested language only', function (): void {
    inEnvironment('local');
    $factory = sampleSmtp();
    configureSmtp(['is_active' => true]);

    $this->artisan('moashir:mail:send-samples', ['code' => 'ACC-09', '--to' => [SAMPLE_INBOX], '--locale' => ['en']])->assertSuccessful();

    $sent = sentSamples($factory);

    expect($sent)->toHaveCount(count(app(MailSamples::class)->forCode(NotificationCode::AccreditedCertificateIssued, Locale::English)))
        ->and(array_filter($sent, static fn (Email $email): bool => str_contains((string) $email->getHtmlBody(), 'class="mail-seal-image"')))->not->toBe([])
        ->and(array_filter($sent, static fn (Email $email): bool => ! str_starts_with((string) $email->getSubject(), 'Moashir')))->toBe([])
        ->and(array_filter($sent, static fn (Email $email): bool => $email->getFrom()[0]->getName() !== 'Moashir Platform'))->toBe([]);
});

it('never sends samples from production, even with working SMTP settings', function (): void {
    inEnvironment('production');
    $factory = sampleSmtp();
    configureSmtp(['is_active' => true]);

    $this->artisan('moashir:mail:send-samples', ['--to' => [SAMPLE_INBOX]])
        ->expectsOutputToContain('never sent from production')
        ->assertFailed();

    expect($factory->sent)->toBe([])
        ->and(sampleAudit())->toBeNull();
});

it('sends samples from staging and local only', function (string $environment): void {
    inEnvironment($environment);
    $factory = sampleSmtp();
    configureSmtp(['is_active' => true]);

    $this->artisan('moashir:mail:send-samples', ['--to' => [SAMPLE_INBOX]])
        ->expectsOutputToContain('staging or local only')
        ->assertFailed();

    expect($factory->sent)->toBe([]);
})->with(['testing', 'demo', 'prod']);

it('explains how to configure e-mail when no SMTP settings are active', function (array $state): void {
    inEnvironment('staging');
    $factory = sampleSmtp();

    if ($state !== []) {
        configureSmtp($state);
    }

    $this->artisan('moashir:mail:send-samples', ['--to' => [SAMPLE_INBOX]])
        ->expectsOutputToContain('Settings > Integrations')
        ->assertFailed();

    expect($factory->sent)->toBe([])
        ->and(sampleAudit())->toBeNull();
})->with([
    'nothing saved' => [[]],
    'saved but inactive' => [['is_active' => false]],
]);

it('refuses invalid input before sending anything', function (array $arguments, string $error): void {
    inEnvironment('staging');
    $factory = sampleSmtp();
    configureSmtp(['is_active' => true]);

    $this->artisan('moashir:mail:send-samples', $arguments)
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect($factory->sent)->toBe([]);
})->with([
    'no inbox' => [[], 'at least one --to'],
    'too many inboxes' => [['--to' => ['a@example.sa', 'b@example.sa', 'c@example.sa', 'd@example.sa']], 'at most 3'],
    'not an address' => [['--to' => ['not-an-address']], 'valid e-mail address'],
    'unknown code' => [['code' => 'XYZ-99', '--to' => [SAMPLE_INBOX]], 'Unknown notification code'],
    'unknown language' => [['--to' => [SAMPLE_INBOX], '--locale' => ['fr']], 'ar and en only'],
]);

it('counts one inbox once when it is given twice', function (): void {
    inEnvironment('staging');
    $factory = sampleSmtp();
    configureSmtp(['is_active' => true]);

    $this->artisan('moashir:mail:send-samples', ['code' => 'REG-02', '--to' => [SAMPLE_INBOX, ' QA.Reviewer@Moashir.example.sa '], '--locale' => ['ar']])->assertSuccessful();

    expect(sentSamples($factory))->toHaveCount(count(app(MailSamples::class)->forCode(NotificationCode::from('REG-02'), Locale::Arabic)));
});

it('stops and records the failure when the mail server refuses a sample', function (): void {
    inEnvironment('staging');
    $factory = sampleSmtp('Expected response code "250" but got code "550", with message "550 5.7.1 Relaying denied".');
    $connection = configureSmtp(['is_active' => true]);

    $this->artisan('moashir:mail:send-samples', ['--to' => [SAMPLE_INBOX]])
        ->expectsOutputToContain('after 0 were sent')
        ->assertFailed();

    $audit = sampleAudit();

    expect($factory->sent)->toBe([])
        ->and($audit?->auditable_id)->toBe($connection->id)
        ->and($audit?->new_values['ok'] ?? null)->toBeFalse()
        ->and($audit?->new_values['sent'] ?? null)->toBe(0)
        ->and($audit?->new_values['error_kind'] ?? null)->toBeString();
});

it('sends through the saved SMTP settings even while the default mailer writes to the log', function (): void {
    inEnvironment('staging');
    $factory = sampleSmtp();
    configureSmtp(['is_active' => true]);
    config(['mail.default' => 'log']);

    $this->artisan('moashir:mail:send-samples', ['code' => 'USR-04', '--to' => [SAMPLE_INBOX], '--locale' => ['en']])->assertSuccessful();

    expect(sentSamples($factory))->not->toBe([])
        ->and(config('mail.default'))->toBe('log');
});
