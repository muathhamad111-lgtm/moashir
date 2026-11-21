<?php

declare(strict_types=1);

use App\Enums\Periods\PeriodCadence;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;
use App\Services\Certificates\VerificationCode;
use App\Support\Certificates\EntityLevelPresenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

function certificateOf(Assessment $assessment): Certificate
{
    return Certificate::query()->where('assessment_id', $assessment->id)->sole();
}

/**
 * @return array<string, string|null>
 */
function levelOf(Entity $entity): array
{
    return EntityLevelPresenter::of(Entity::query()->with('levelCertificate')->findOrFail($entity->id));
}

it('makes a certificate valid until the end of the period after the request period', function (string $cadence, string $submittedOn, string $validUntil): void {
    atRiyadh('2027-01-02 10:00');
    livePeriods(cadence: PeriodCadence::from($cadence));
    $version = publishedAccreditedCriteria();
    atRiyadh($submittedOn);
    $completed = completedInPeriod($version, assessorUser());

    $certificate = certificateOf($completed);
    expect($certificate->valid_until?->toDateString())->toBe($validUntil);

    switchTo(ownerOf($completed->entity))->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()
        ->assertJsonPath('data.valid_until', $validUntil)
        ->assertJsonPath('data.is_expired', false);
})->with([
    'quarterly' => ['quarterly', '2027-02-10 10:00', '2027-06-30'],
    'semi-annual' => ['semi_annual', '2027-05-10 10:00', '2027-12-31'],
    'annual' => ['annual', '2027-11-10 10:00', '2028-12-31'],
]);

it('keeps the first rollout period as the validity of a result submitted before the rollout', function (): void {
    atRiyadh('2026-12-10 10:00');
    livePeriods();
    $completed = completedInPeriod(publishedAccreditedCriteria(), assessorUser());

    expect(certificateOf($completed)->valid_until?->toDateString())->toBe('2027-03-31')
        ->and($completed->period_id)->toBeNull();
});

it('takes the entity level from the latest valid certificate and drops it after it expires', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $completed = completedInPeriod(publishedAccreditedCriteria(), assessorUser());
    $certificate = certificateOf($completed);

    expect(levelOf($completed->entity))->toBe(['level' => 'silver', 'level_certificate_number' => $certificate->number, 'level_valid_until' => '2027-06-30']);

    atRiyadh('2027-06-30 23:59');
    expect(levelOf($completed->entity)['level'])->toBe('silver');

    atRiyadh('2027-07-01 00:01');
    expect(levelOf($completed->entity))->toBe(['level' => null, 'level_certificate_number' => null, 'level_valid_until' => null]);

    $board = boardOf(ownerOf($completed->entity), 'entity');
    expect(boardWidget($board, 'level'))->toBe(['level' => null, 'certificate' => null]);
});

it('falls back to the previous valid certificate when the latest one is revoked', function (): void {
    atRiyadh('2026-12-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $older = completedInPeriod($version, $assessor);
    atRiyadh('2027-02-10 10:00');
    $newer = completedInPeriod($version, $assessor, $older->entity);
    $newerCertificate = certificateOf($newer);

    expect(levelOf($older->entity)['level_certificate_number'])->toBe($newerCertificate->number);

    switchTo(systemAdmin())->postJson("/api/v1/certificates/{$newerCertificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertOk();

    expect(levelOf($older->entity))->toBe(['level' => 'silver', 'level_certificate_number' => certificateOf($older)->number, 'level_valid_until' => '2027-03-31']);
});

it('shows an expired certificate as expired on the public verification page', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $certificate = certificateOf(completedInPeriod(publishedAccreditedCriteria(), assessorUser()));
    $code = VerificationCode::display($certificate->verification_code);

    atRiyadh('2027-07-01 09:00');
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/verify/'.$code)->assertOk()
        ->assertJsonPath('data.result', 'expired')
        ->assertJsonPath('data.certificate.number', $certificate->number)
        ->assertJsonPath('data.certificate.valid_until', '2027-06-30');

    switchTo(ownerOf($certificate->entity))->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.is_expired', true)
        ->assertJsonPath('data.valid_until_at', '2027-06-30T21:00:00+00:00');
});

it('keeps the validity of a certificate immutable in the database', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $certificate = certificateOf(completedInPeriod(publishedAccreditedCriteria(), assessorUser()));

    expect(fn () => Certificate::query()->whereKey($certificate->id)->update(['valid_until' => '2030-12-31']))
        ->toThrow(QueryException::class);
});
