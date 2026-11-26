<?php

declare(strict_types=1);

use App\Actions\Award\IssueCycleCertificates;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Award\AwardCycle;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Services\Certificates\VerificationCode;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return list<string>
 */
function awardCertificateKindsOf(Assessment $assessment): array
{
    $kinds = Certificate::query()->where('assessment_id', $assessment->id)->pluck('kind')
        ->map(static fn (mixed $kind): string => $kind instanceof BackedEnum ? (string) $kind->value : (string) $kind)
        ->sort()->values()->all();

    return array_values($kinds);
}

/**
 * @return list<string>
 */
function awardCertificateNoticesOf(Assessment $assessment): array
{
    return array_values(array_map(
        static fn (EntityNotification $notification): string => $notification->notice->code->value,
        Notification::sent(ownerOf($assessment->entity), EntityNotification::class)->all(),
    ));
}

function awardCertificatePath(AwardCycle $cycle, string $suffix): string
{
    return "/api/v1/award/cycles/{$cycle->id}/{$suffix}";
}

/**
 * A cycle taken to its approved national result: the national winner (middle), a regional winner the secretary did
 * not choose nationally (top), an approved non-winner (low) and a request the filtering officer did not qualify.
 *
 * @return array{cycle: AwardCycle, secretary: User, top: Assessment, middle: Assessment, low: Assessment, rejected: Assessment}
 */
function nationallyApprovedAwardCycle(bool $participation): array
{
    [$cycle, $version, $secretary, $officer] = committeeCycle();
    $cycle->forceFill(['participation_certificate_enabled' => $participation])->save();
    $requests = [];

    for ($index = 0; $index < 4; $index++) {
        $requests[] = initiallyAssessedRequest($cycle, $version, assessorUser());
    }

    closeCycleSubmissions($cycle);
    [$top, $middle, $low, $rejected] = $requests;

    foreach ([$top, $middle, $low] as $assessment) {
        qualifyRequest($cycle, $officer, $assessment);
    }

    switchTo($officer)->postJson(awardCertificatePath($cycle, "filtering/{$rejected->id}/disqualify"), ['reason' => DISQUALIFY_REASON])->assertOk();
    $assessors = appointCommittee($cycle, $secretary, 1);
    startCommittee($cycle, $secretary)->assertOk();
    $scenario = ['cycle' => $cycle->refresh(), 'version' => $version, 'secretary' => $secretary, 'officer' => $officer, 'assessors' => $assessors, 'requests' => [$top, $middle, $low]];
    approvedByCommittee($scenario, $top, COMMITTEE_TOP_MARKS);
    approvedByCommittee($scenario, $middle);
    approvedByCommittee($scenario, $low, COMMITTEE_MID_MARKS);

    $region = $cycle->regions->firstOrFail()->id;
    switchTo($secretary)->putJson(awardCertificatePath($cycle, "results/{$top->id}/regional-rank"), ['rank' => 1])->assertOk();
    test()->putJson(awardCertificatePath($cycle, "results/{$middle->id}/regional-rank"), ['rank' => 2])->assertOk();
    test()->postJson(awardCertificatePath($cycle, "regions/{$region}/approve"))->assertOk();
    test()->putJson(awardCertificatePath($cycle, "national/{$middle->id}/rank"), ['rank' => 1])->assertOk();
    test()->postJson(awardCertificatePath($cycle, 'national/approve'))->assertOk();

    return ['cycle' => $cycle->refresh(), 'secretary' => $secretary, 'top' => $top->refresh(), 'middle' => $middle->refresh(), 'low' => $low->refresh(), 'rejected' => $rejected->refresh()];
}

it('issues the participation certificate as soon as the initial assessment completes, when the cycle gives it', function (): void {
    $version = publishedAccreditedCriteria();
    $giving = openAwardCycle($version, ['participation_certificate_enabled' => true]);
    $assessed = initiallyAssessedRequest($giving, $version, assessorUser());

    $certificate = Certificate::query()->where('assessment_id', $assessed->id)->sole();
    expect($certificate->kind->value)->toBe('advanced_participation')
        ->and($certificate->level)->toBeNull()
        ->and($certificate->valid_until)->toBeNull()
        ->and($certificate->snapshot)->not->toHaveKey('percent')
        ->and($certificate->snapshot['award']['cycle_name']['ar'])->toBe($giving->name->ar)
        ->and($certificate->snapshot['award']['region'])->toBeNull()
        ->and(awardCertificateNoticesOf($assessed))->toContain(NotificationCode::AdvancedParticipationCertificateIssued->value)
        ->and(awardCertificateNoticesOf($assessed))->not->toContain(NotificationCode::AccreditedCertificateIssued->value)
        ->and(Audit::query()->where('event', AuditEvent::CertificateIssued->value)->where('auditable_id', $certificate->id)->exists())->toBeTrue();
});

it('issues no participation certificate when the cycle gives none', function (): void {
    $version = publishedAccreditedCriteria();
    $quiet = openAwardCycle($version, ['participation_certificate_enabled' => false]);
    $assessed = initiallyAssessedRequest($quiet, $version, assessorUser());

    expect(awardCertificateKindsOf($assessed))->toBe([])
        ->and(awardCertificateNoticesOf($assessed))->not->toContain(NotificationCode::AdvancedParticipationCertificateIssued->value);
});

it('keeps the participation certificate of a request withdrawn after its initial assessment and gives none before it', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version, ['participation_certificate_enabled' => true]);
    $assessed = initiallyAssessedRequest($cycle, $version, assessorUser());
    $early = startedInitialAssessment($cycle, $version, assessorUser());
    $follower = requestFollower();

    switchTo($follower)->postJson("/api/v1/requests/{$assessed->id}/withdraw", ['reason' => WITHDRAWAL_REASON])->assertOk()->assertJsonPath('data.status', 'withdrawn');
    test()->postJson("/api/v1/requests/{$early->id}/withdraw", ['reason' => WITHDRAWAL_REASON])->assertOk()->assertJsonPath('data.status', 'withdrawn');
    app(IssueCycleCertificates::class)->handle($cycle->refresh());

    expect(awardCertificateKindsOf($assessed))->toBe(['advanced_participation'])
        ->and(Certificate::query()->where('assessment_id', $assessed->id)->sole()->status->value)->toBe('issued')
        ->and(awardCertificateKindsOf($early))->toBe([]);
});

it('issues the winner and qualified certificates only at the announcement, next to participation, and only once', function (): void {
    $results = nationallyApprovedAwardCycle(true);
    $cycle = $results['cycle'];

    foreach (['top', 'middle', 'low', 'rejected'] as $name) {
        expect(awardCertificateKindsOf($results[$name]))->toBe(['advanced_participation'])
            ->and(awardCertificateNoticesOf($results[$name]))->not->toContain(NotificationCode::AdvancedCertificateIssued->value);
    }

    switchTo($results['secretary'])->postJson(awardCertificatePath($cycle, 'announce'))->assertOk();

    expect(awardCertificateKindsOf($results['middle']))->toBe(['advanced_participation', 'advanced_winner'])
        ->and(awardCertificateKindsOf($results['top']))->toBe(['advanced_participation', 'advanced_winner'])
        ->and(awardCertificateKindsOf($results['low']))->toBe(['advanced_participation', 'advanced_qualified'])
        ->and(awardCertificateKindsOf($results['rejected']))->toBe(['advanced_participation'])
        ->and(awardCertificateNoticesOf($results['middle']))->toContain(NotificationCode::AdvancedCertificateIssued->value)
        ->and(awardCertificateNoticesOf($results['rejected']))->not->toContain(NotificationCode::AdvancedCertificateIssued->value);

    $winner = Certificate::query()->where('assessment_id', $results['middle']->id)->where('kind', 'advanced_winner')->sole();
    expect($winner->snapshot['award']['national_rank'])->toBe(1)
        ->and($winner->snapshot['award']['regional_rank'])->toBe(2)
        ->and($winner->snapshot)->not->toHaveKey('percent');

    $count = Certificate::query()->count();
    app(IssueCycleCertificates::class)->handle($cycle->refresh());
    expect(Certificate::query()->count())->toBe($count);
});

it('gives only the outcome certificates when the cycle gives no participation certificate', function (): void {
    $results = nationallyApprovedAwardCycle(false);
    expect(Certificate::query()->count())->toBe(0);

    switchTo($results['secretary'])->postJson(awardCertificatePath($results['cycle'], 'announce'))->assertOk();

    expect(awardCertificateKindsOf($results['middle']))->toBe(['advanced_winner'])
        ->and(awardCertificateKindsOf($results['low']))->toBe(['advanced_qualified'])
        ->and(awardCertificateKindsOf($results['rejected']))->toBe([]);
});

it('backfills participation certificates when the setting is turned on, skipping a frozen request', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version, ['participation_certificate_enabled' => false]);
    $assessed = initiallyAssessedRequest($cycle, $version, assessorUser());
    $withdrawn = initiallyAssessedRequest($cycle, $version, assessorUser());
    $frozen = initiallyAssessedRequest($cycle, $version, assessorUser());
    $pending = startedInitialAssessment($cycle, $version, assessorUser());
    switchTo(requestFollower())->postJson("/api/v1/requests/{$withdrawn->id}/withdraw", ['reason' => WITHDRAWAL_REASON])->assertOk();
    $frozen->entity->delete();
    expect(Certificate::query()->count())->toBe(0);

    switchTo(systemAdmin())->putJson("/api/v1/award/cycles/{$cycle->id}", ['participation_certificate_enabled' => true])->assertOk();
    test()->putJson("/api/v1/award/cycles/{$cycle->id}", ['participation_certificate_enabled' => true])->assertOk();

    expect(awardCertificateKindsOf($assessed))->toBe(['advanced_participation'])
        ->and(awardCertificateKindsOf($withdrawn))->toBe(['advanced_participation'])
        ->and(awardCertificateKindsOf($frozen))->toBe([])
        ->and(awardCertificateKindsOf($pending))->toBe([])
        ->and(Certificate::query()->count())->toBe(2);
});

it('shows no score on an award certificate, to its entity or on the public verification page', function (): void {
    $results = nationallyApprovedAwardCycle(true);
    switchTo($results['secretary'])->postJson(awardCertificatePath($results['cycle'], 'announce'))->assertOk();
    $winner = Certificate::query()->where('assessment_id', $results['middle']->id)->where('kind', 'advanced_winner')->sole();

    switchTo(ownerOf($results['middle']->entity))->getJson('/api/v1/certificates?filter[kind]=accredited')->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);
    test()->getJson('/api/v1/certificates?filter[kind]=advanced_winner')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
    test()->getJson('/api/v1/certificates?filter[kind]=unknown')->assertUnprocessable();
    test()->getJson('/api/v1/certificates')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.percent', null)
        ->assertJsonPath('data.1.percent', null);
    test()->getJson("/api/v1/assessments/{$results['middle']->id}")->assertOk()
        ->assertJsonPath('data.certificate.number', $winner->number)
        ->assertJsonPath('data.certificate.kind', 'advanced_winner');
    switchTo(ownerOf($results['low']->entity))->getJson("/api/v1/certificates/{$winner->public_id}")->assertNotFound();

    $this->app['auth']->forgetGuards();
    $response = $this->getJson('/api/v1/verify/'.VerificationCode::display($winner->verification_code))->assertOk()
        ->assertJsonPath('data.result', 'valid')
        ->assertJsonPath('data.certificate.kind', 'advanced_winner')
        ->assertJsonPath('data.certificate.percent', null)
        ->assertJsonPath('data.certificate.level', null)
        ->assertJsonPath('data.certificate.award.national_rank', 1);

    expect($response->json('data.certificate.award'))->toHaveKeys(['cycle_name', 'cycle_year', 'region', 'regional_rank', 'national_rank'])
        ->and(array_keys((array) $response->json('data.certificate.award')))->not->toContain('percent', 'average', 'score');
});
