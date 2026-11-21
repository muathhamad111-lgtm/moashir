<?php

declare(strict_types=1);

use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

function validityCertificate(): Certificate
{
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $completed = completedInPeriod(publishedAccreditedCriteria(), assessorUser());

    return Certificate::query()->where('assessment_id', $completed->id)->sole();
}

it('derives the validity of the own certificate from its expiry date on every read', function (string $today, string $validity, ?int $daysLeft): void {
    $certificate = validityCertificate();
    $owner = ownerOf($certificate->entity);
    atRiyadh($today);

    switchTo($owner)->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()
        ->assertJsonPath('data.valid_until', '2027-06-30')
        ->assertJsonPath('data.validity', $validity)
        ->assertJsonPath('data.days_until_expiry', $daysLeft);

    switchTo($owner)->getJson('/api/v1/certificates?filter[status]=issued')->assertOk()
        ->assertJsonPath('data.0.validity', $validity)
        ->assertJsonPath('data.0.days_until_expiry', $daysLeft);
})->with([
    '31 days left' => ['2027-05-30 23:00', 'valid', 31],
    '30 days left' => ['2027-05-31 00:30', 'expiring_soon', 30],
    'last valid day' => ['2027-06-30 23:59', 'expiring_soon', 0],
    'past the date' => ['2027-07-01 00:01', 'expired', null],
]);

it('keeps the stored certificate unchanged while the validity moves with the date', function (): void {
    $certificate = validityCertificate();
    atRiyadh('2027-07-01 09:00');

    switchTo(ownerOf($certificate->entity))->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.is_expired', true)
        ->assertJsonPath('data.validity', 'expired');

    expect($certificate->refresh()->status->value)->toBe('issued')
        ->and(Schema::hasColumn('certificates', 'validity'))->toBeFalse();
});

it('reads the expiring-soon threshold from the configuration', function (): void {
    $certificate = validityCertificate();
    config()->set('moashir.certificates.expiring_soon_days', 60);
    atRiyadh('2027-05-10 10:00');

    switchTo(ownerOf($certificate->entity))->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()
        ->assertJsonPath('data.validity', 'expiring_soon')
        ->assertJsonPath('data.days_until_expiry', 51);
});

it('shows the same validity to staff with the certificates permission', function (): void {
    $certificate = validityCertificate();
    atRiyadh('2027-06-15 10:00');

    switchTo(systemAdmin())->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()
        ->assertJsonPath('data.validity', 'expiring_soon')
        ->assertJsonPath('data.days_until_expiry', 15);
});

it('carries the validity in the certificate summary of the own request', function (): void {
    $certificate = validityCertificate();
    atRiyadh('2027-06-20 10:00');

    $assessment = Assessment::query()->findOrFail($certificate->assessment_id);

    switchTo(ownerOf($certificate->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.certificate.validity', 'expiring_soon')
        ->assertJsonPath('data.certificate.days_until_expiry', 10);
});

it('does not disclose the validity of another entity certificate', function (): void {
    $certificate = validityCertificate();
    atRiyadh('2027-06-20 10:00');

    switchTo(ownerOf(entityWithOwner()))->getJson("/api/v1/certificates/{$certificate->public_id}")->assertNotFound();
});

it('rejects an unknown status filter on the list that carries the validity', function (): void {
    $certificate = validityCertificate();

    switchTo(ownerOf($certificate->entity))->getJson('/api/v1/certificates?filter[status]=expiring_soon')->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
});
