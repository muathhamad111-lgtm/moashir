<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use App\Models\Finance\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('issues the accredited certificate when the assessor completes the assessment', function (): void {
    $admin = systemAdmin();
    ['assessment' => $assessment, 'certificate' => $certificate, 'assessor' => $assessor] = certifiedAccreditedRequest();
    $year = Date::now(config()->string('app.display_timezone'))->year;

    expect($certificate->number)->toBe(sprintf('CRT-%d-000001', $year))
        ->and($certificate->status->value)->toBe('issued')
        ->and($certificate->level?->value)->toBe('silver')
        ->and($certificate->is_test)->toBeFalse()
        ->and($certificate->issued_by_user_id)->toBe($assessor->id)
        ->and($certificate->score_percent->toDisplay())->toBe('77.00')
        ->and($certificate->verification_code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{28}$/')
        ->and($certificate->snapshot['entity_name'])->toBe($assessment->entity->name->toArray())
        ->and($certificate->snapshot['level_thresholds'])->toEqual(['bronze' => '60.00', 'silver' => '70.00', 'gold' => '80.00', 'diamond' => '90.00'])
        ->and($certificate->pdf_file_id)->not->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::CertificateIssued->value)->count())->toBe(1)
        ->and(financeNoticeSent(ownerOf($assessment->entity), NotificationCode::AccreditedCertificateIssued))->toBeTrue()
        ->and(financeNoticeSent($admin, NotificationCode::AccreditedCertificateIssued))->toBeTrue();

    $document = collect(app(FakePdfRenderer::class)->rendered())->firstWhere('template', 'accredited_certificate');
    expect($document?->data['level'])->toBe('silver')
        ->and($document?->data['percent'])->toBe('77.00')
        ->and($document?->data['issue_year'])->toBe((string) $year)
        ->and($document?->data['verification_url'])->toEndWith('/verify/'.$certificate->verification_code)
        ->and($document?->watermark)->toBeNull();
});

it('applies the edited level thresholds, down to a participation certificate below bronze', function (array $thresholds, ?string $level): void {
    setLevelThresholds($thresholds);

    ['certificate' => $certificate] = certifiedAccreditedRequest();

    expect($certificate->level?->value)->toBe($level);
})->with([
    'gold' => [['bronze' => '50.00', 'silver' => '60.00', 'gold' => '75.00', 'diamond' => '95.00'], 'gold'],
    'participation' => [['bronze' => '80.00', 'silver' => '85.00', 'gold' => '90.00', 'diamond' => '95.00'], null],
]);

it('lets the assessor complete without a certificate and issue it later, once', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete", ['issue_certificate' => false])->assertOk();
    expect(Certificate::query()->count())->toBe(0);

    $created = switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertCreated()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.request.number', $assessment->request_number)
        ->assertJsonPath('data.issued_by.id', $assessor->id)
        ->json('data.id');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertOk()->assertJsonPath('data.id', $created);
    expect(Certificate::query()->count())->toBe(1);
});

it('lets only the assessor who completed the request issue its certificate', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete", ['issue_certificate' => false])->assertOk();

    switchTo(assessorUser())->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertNotFound();
    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertForbidden();
    switchTo(systemAdmin())->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertForbidden();
    switchTo(staffUser(RoleKey::FinanceOfficer))->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertNotFound();

    expect(Certificate::query()->count())->toBe(0);
});

it('refuses a certificate for a request that is not completed', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/certificate")
        ->assertConflict()
        ->assertJsonPath('code', 'certificate.assessment_not_completed');
});

it('issues nothing while accredited certificate issuance is turned off', function (): void {
    setAccreditedCertificateIssuance(false);
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = completedAccreditedRequest($version, $assessor);

    expect(Certificate::query()->count())->toBe(0);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/certificate")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'certificate.issuance_disabled');
});

it('marks the certificate of a request opened by a TEST payment with a TEST number and watermark', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);
    Payment::query()->whereKey($assessment->payment_id)->update(['mode' => IntegrationMode::Test->value]);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();
    $certificate = Certificate::query()->sole();

    expect($certificate->is_test)->toBeTrue()
        ->and($certificate->number)->toMatch('/^TEST-CRT-\d{4}-000001$/')
        ->and(collect(app(FakePdfRenderer::class)->rendered())->firstWhere('template', 'accredited_certificate')?->watermark)->toBe('TEST');
});

it('asks the Super Admin for a reason before issuing a certificate', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete", ['issue_certificate' => false])->assertOk();
    $superAdmin = superAdmin();

    switchTo($superAdmin)->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    switchTo($superAdmin)->postJson("/api/v1/assessments/{$assessment->id}/certificate", ['reason' => CERTIFICATE_REASON])->assertCreated();

    expect(Audit::query()->where('event', AuditEvent::CertificateIssued->value)->sole()->reason)->toBe(CERTIFICATE_REASON);
});

it('keeps the number, verification code and result of a certificate immutable and never deletes it', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();

    expect(fn () => Certificate::query()->whereKey($certificate->id)->update(['number' => 'CRT-2026-999999']))->toThrow(QueryException::class)
        ->and(fn () => Certificate::query()->whereKey($certificate->id)->update(['score_percent' => '99.0000']))->toThrow(QueryException::class)
        ->and(fn () => $certificate->delete())->toThrow(LogicException::class)
        ->and(fn () => Certificate::query()->whereKey($certificate->id)->toBase()->delete())->toThrow(QueryException::class);
});
