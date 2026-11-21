<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Identity\RoleKey;
use App\Models\Entities\Entity;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

const PERIODIC_ACCREDITED_CURRENT = '/api/v1/assessments/accredited/current';

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

function accreditedCurrentOf(Entity $entity): TestResponse
{
    return switchTo(ownerOf($entity))->getJson(PERIODIC_ACCREDITED_CURRENT)->assertOk();
}

it('describes the current period, the cadence and the next period of an entity that has not submitted', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    setServicePrices('1000.00');
    $entity = entityWithOwner();

    accreditedCurrentOf($entity)
        ->assertJsonPath('data.periodic.is_live', true)
        ->assertJsonPath('data.periodic.rollout_from', '2027-01-01')
        ->assertJsonPath('data.periodic.cadence', 'quarterly')
        ->assertJsonPath('data.periodic.cadence_effective_from', null)
        ->assertJsonPath('data.periodic.current_period.key', '2027-Q1')
        ->assertJsonPath('data.periodic.current_period.label', ['ar' => 'الربع الأول 2027', 'en' => 'Q1 2027'])
        ->assertJsonPath('data.periodic.current_period.starts_on', '2027-01-01')
        ->assertJsonPath('data.periodic.current_period.ends_on', '2027-03-31')
        ->assertJsonPath('data.periodic.current_period.starts_at', '2026-12-31T21:00:00+00:00')
        ->assertJsonPath('data.periodic.current_period.ends_at', '2027-03-31T21:00:00+00:00')
        ->assertJsonPath('data.periodic.current_period.is_shortened', false)
        ->assertJsonPath('data.periodic.period_status', 'not_submitted')
        ->assertJsonPath('data.periodic.period_request', null)
        ->assertJsonPath('data.periodic.next_period.key', '2027-Q2')
        ->assertJsonPath('data.periodic.next_period.notice_on', '2027-03-18')
        ->assertJsonPath('data.periodic.next_submission_from', null)
        ->assertJsonPath('data.submission', ['can_submit_now' => false, 'reason' => 'no_open_draft', 'next_submission_from' => null])
        ->assertJsonPath('data.apply', ['can_start' => true, 'reason' => null, 'warning' => null])
        ->assertJsonPath('data.pricing.is_free', false)
        ->assertJsonPath('data.pricing.availability', 'available')
        ->assertJsonPath('data.pricing.price.price_before_vat', '1000.00')
        ->assertJsonPath('data.pricing.price.total', '1150.00');
});

it('blocks the submission and warns before paying once the period is assessed', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    setServicePrices('1000.00');
    $version = publishedAccreditedCriteria();
    $completed = completedInPeriod($version, assessorUser());

    accreditedCurrentOf($completed->entity)
        ->assertJsonPath('data.periodic.period_status', 'assessed')
        ->assertJsonPath('data.periodic.period_request.id', $completed->id)
        ->assertJsonPath('data.periodic.period_request.status', 'completed')
        ->assertJsonPath('data.periodic.next_submission_from', '2027-04-01')
        ->assertJsonPath('data.apply', ['can_start' => true, 'reason' => null, 'warning' => 'period_already_assessed'])
        ->assertJsonPath('data.latest_closed.period_state', 'assigned')
        ->assertJsonPath('data.latest_closed.period.key', '2027-Q1')
        ->assertJsonPath('data.latest_closed.certificate.valid_until', '2027-06-30');

    $draft = readyAccreditedDraft($version, $completed->entity);

    accreditedCurrentOf($completed->entity)
        ->assertJsonPath('data.active.id', $draft->id)
        ->assertJsonPath('data.active.period_state', 'pending')
        ->assertJsonPath('data.submission', ['can_submit_now' => false, 'reason' => 'period_already_assessed', 'next_submission_from' => '2027-04-01'])
        ->assertJsonPath('data.apply.reason', 'open_request_exists');
});

it('reports a draft that can be submitted now and one that is still incomplete', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $ready = readyAccreditedDraft($version);
    $incomplete = openedAccreditedRequest();

    accreditedCurrentOf($ready->entity)->assertJsonPath('data.submission.can_submit_now', true)->assertJsonPath('data.submission.reason', null);
    accreditedCurrentOf($incomplete->entity)->assertJsonPath('data.submission.reason', 'incomplete');
});

it('shows the period in review and the closed without result status', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $submitted = submitDraft(readyAccreditedDraft($version));

    accreditedCurrentOf($submitted->entity)
        ->assertJsonPath('data.periodic.period_status', 'in_progress')
        ->assertJsonPath('data.active.period.key', '2027-Q1');

    $submitted->forceFill(['status' => AccreditedAssessmentStatus::Refunded, 'refunded_at' => Date::now()])->save();
    accreditedCurrentOf($submitted->entity)->assertJsonPath('data.periodic.period_status', 'closed_without_result');
});

it('reports no live period before the rollout and a pending cadence change', function (): void {
    atRiyadh('2026-12-01 10:00');
    livePeriods();
    $entity = entityWithOwner();

    accreditedCurrentOf($entity)
        ->assertJsonPath('data.periodic.is_live', false)
        ->assertJsonPath('data.periodic.current_period', null)
        ->assertJsonPath('data.periodic.period_status', null)
        ->assertJsonPath('data.periodic.next_period.key', '2027-Q1')
        ->assertJsonPath('data.pricing.availability', 'paused')
        ->assertJsonPath('data.apply.reason', 'path_unavailable');

    atRiyadh('2027-02-10 10:00');
    generatePeriods();
    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment/periodic', ['cadence' => 'annual'])->assertOk();

    accreditedCurrentOf($entity)
        ->assertJsonPath('data.periodic.cadence', 'annual')
        ->assertJsonPath('data.periodic.cadence_effective_from', '2027-04-01')
        ->assertJsonPath('data.periodic.next_period.key', '2027')
        ->assertJsonPath('data.periodic.next_period.is_shortened', true);
});

it('denies the accredited current view to staff and guests', function (): void {
    test()->getJson(PERIODIC_ACCREDITED_CURRENT)->assertUnauthorized();
    switchTo(systemAdmin())->getJson(PERIODIC_ACCREDITED_CURRENT)->assertForbidden();
    switchTo(staffUser(RoleKey::RequestFollower))->getJson(PERIODIC_ACCREDITED_CURRENT)->assertForbidden();
});
