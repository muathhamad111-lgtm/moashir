<?php

declare(strict_types=1);

use App\Enums\Integrations\IntegrationMode;
use App\Models\Certificates\Certificate;
use App\Models\Certificates\CertificateVerificationLog;
use App\Models\Finance\Payment;
use App\Services\Certificates\VerificationCode;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('verifies a valid certificate publicly with the entity name and level but nothing private', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/v1/verify/'.VerificationCode::display($certificate->verification_code))->assertOk()
        ->assertJsonPath('data.result', 'valid')
        ->assertJsonPath('data.certificate.number', $certificate->number)
        ->assertJsonPath('data.certificate.level', 'silver')
        ->assertJsonPath('data.certificate.percent', '77.00')
        ->assertJsonPath('data.certificate.entity_name.ar', $assessment->entity->name->ar);

    expect($response->json('data.certificate'))->not->toHaveKeys(['verification_code', 'revoked_reason', 'issued_by', 'entity', 'request', 'id']);

    $log = CertificateVerificationLog::query()->sole();
    expect($log->outcome->value)->toBe('valid')
        ->and($log->certificate_id)->toBe($certificate->id)
        ->and($log->ip_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($log->ip_hash)->not->toContain('127.0.0.1');
});

it('shows a revoked certificate as revoked without the reason', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();
    switchTo(systemAdmin())->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertOk();
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/v1/verify/'.strtolower($certificate->verification_code))->assertOk()
        ->assertJsonPath('data.result', 'revoked')
        ->assertJsonPath('data.certificate.number', $certificate->number);

    expect($response->getContent())->not->toContain(CERTIFICATE_REASON);
});

it('answers unverifiable without any certificate detail when the request was opened by a test-mode payment', function (): void {
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview(publishedAccreditedCriteria(), $assessor);
    Payment::query()->whereKey($assessment->payment_id)->update(['mode' => IntegrationMode::Test->value]);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();
    $certificate = Certificate::query()->sole();
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/v1/verify/'.VerificationCode::display($certificate->verification_code))->assertOk()
        ->assertJsonPath('data.result', 'unverifiable')
        ->assertJsonPath('data.certificate', null);

    $log = CertificateVerificationLog::query()->sole();
    expect($log->outcome->value)->toBe('unverifiable')
        ->and($log->certificate_id)->toBe($certificate->id)
        ->and($response->getContent())->not->toContain($certificate->number)
        ->and($response->getContent())->not->toContain('is_test');
});

it('never exposes the internal test flag on a verifiable certificate', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/v1/verify/'.VerificationCode::display($certificate->verification_code))->assertOk()
        ->assertJsonPath('data.result', 'valid');

    expect($response->json('data.certificate'))->not->toHaveKey('is_test');
});

it('answers not_found with 200 for unknown or malformed codes', function (string $code): void {
    $this->getJson('/api/v1/verify/'.$code)->assertOk()
        ->assertJsonPath('data.result', 'not_found')
        ->assertJsonPath('data.certificate', null);

    expect(CertificateVerificationLog::query()->sole()->outcome->value)->toBe('not_found');
})->with(['0000000000000000000000000000', 'ABC', 'K7Q4-9XPM']);

it('rate limits the public verification per address', function (): void {
    foreach (range(1, 30) as $attempt) {
        $this->getJson('/api/v1/verify/0000000000000000000000000000')->assertOk();
    }

    $this->getJson('/api/v1/verify/0000000000000000000000000000')->assertTooManyRequests();
});

it('prunes verification logs older than the retention period', function (): void {
    $this->getJson('/api/v1/verify/0000000000000000000000000000')->assertOk();
    Date::setTestNow(Date::now()->addDays(366));
    $this->getJson('/api/v1/verify/0000000000000000000000000000')->assertOk();

    $this->artisan('moashir:certificates:prune-verification-logs')->assertSuccessful();

    expect(CertificateVerificationLog::query()->count())->toBe(1);
});
