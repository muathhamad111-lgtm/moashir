<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

it('merges the missed periods into the accredited request list of an entity, newest first, and paginates both', function (): void {
    atRiyadh('2027-01-05 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $entity = entityWithOwner();
    atRiyadh('2027-04-01 00:20');
    generatePeriods();
    closePeriods();
    atRiyadh('2027-04-10 10:00');
    $completed = completedInPeriod($version, assessorUser(), $entity);
    $owner = ownerOf($entity);

    $first = switchTo($owner)->getJson('/api/v1/assessments?filter[path]=accredited&per_page=1')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.pagination.last_page', 2)
        ->assertJsonPath('meta.missed_periods', 1)
        ->assertJsonPath('meta.counts.total', 1)
        ->assertJsonPath('data.0.row_type', 'request')
        ->assertJsonPath('data.0.row_key', "request-{$completed->id}")
        ->assertJsonPath('data.0.period_state', 'assigned')
        ->assertJsonPath('data.0.period.key', '2027-Q2')
        ->assertJsonPath('data.0.period_label.en', 'Q2 2027')
        ->assertJsonPath('data.0.payment.status', 'paid')
        ->assertJsonPath('data.0.level', 'silver')
        ->assertJsonPath('data.0.certificate.valid_until', '2027-09-30')
        ->assertJsonPath('data.0.certificate.is_expired', false)
        ->assertJsonPath('data.0.kit', ['status' => null, 'request_id' => null]);

    expect($first->json('data.0.result.percent'))->not->toBeNull();

    $q1 = periodByKey('2027-Q1');
    switchTo($owner)->getJson('/api/v1/assessments?filter[path]=accredited&per_page=1&page=2')->assertOk()
        ->assertJsonPath('data.0', [
            'row_type' => 'missed_period',
            'row_key' => "period-{$q1->id}",
            'id' => null,
            'number' => null,
            'path' => 'accredited',
            'path_label' => ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment'],
            'status' => null,
            'period_status' => 'missed',
            'period_state' => 'assigned',
            'period' => [
                'id' => $q1->id,
                'key' => '2027-Q1',
                'cadence' => 'quarterly',
                'label' => ['ar' => 'الربع الأول 2027', 'en' => 'Q1 2027'],
                'starts_on' => '2027-01-01',
                'ends_on' => '2027-03-31',
                'starts_at' => '2026-12-31T21:00:00+00:00',
                'ends_at' => '2027-03-31T21:00:00+00:00',
                'is_shortened' => false,
            ],
            'period_label' => ['ar' => 'الربع الأول 2027', 'en' => 'Q1 2027'],
        ]);

    switchTo($owner)->getJson('/api/v1/assessments?filter[path]=accredited&sort=created_at')->assertOk()
        ->assertJsonPath('data.0.row_type', 'missed_period')
        ->assertJsonPath('data.1.row_type', 'request');
});

it('lists only requests when a filter or another path is chosen', function (): void {
    atRiyadh('2027-01-05 10:00');
    livePeriods();
    $entity = entityWithOwner();
    atRiyadh('2027-04-01 00:20');
    generatePeriods();
    closePeriods();
    $owner = ownerOf($entity);

    foreach (['filter[path]=accredited&filter[status]=completed', 'filter[path]=accredited&filter[is_open]=true', 'filter[path]=self', 'filter[path]=accredited&sort=-submitted_at'] as $query) {
        switchTo($owner)->getJson("/api/v1/assessments?{$query}")->assertOk()
            ->assertJsonPath('meta.missed_periods', 0)
            ->assertJsonPath('meta.pagination.total', 0);
    }
});

it('shows the missed periods of an entity to the request queue staff but not to an assessor', function (): void {
    atRiyadh('2027-01-05 10:00');
    livePeriods();
    $entity = entityWithOwner();
    atRiyadh('2027-04-01 00:20');
    generatePeriods();
    closePeriods();

    switchTo(requestFollower())->getJson("/api/v1/assessments?filter[path]=accredited&filter[entity_id]={$entity->id}")->assertOk()
        ->assertJsonPath('meta.missed_periods', 1)
        ->assertJsonPath('data.0.row_type', 'missed_period');
    switchTo(assessorUser())->getJson("/api/v1/assessments?filter[path]=accredited&filter[entity_id]={$entity->id}")->assertOk()
        ->assertJsonPath('meta.missed_periods', 0)
        ->assertJsonPath('data', []);
});

it('lists the tabs of an accredited request detail by its state', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $completed = completedInPeriod($version, assessorUser());
    $draft = openedAccreditedRequest();
    $self = startedSelfAssessment(ownerOf(entityWithOwner()));

    switchTo(ownerOf($completed->entity))->getJson("/api/v1/assessments/{$completed->id}")->assertOk()
        ->assertJsonPath('data.available_tabs', ['request', 'result', 'feedback', 'certificate', 'tools'])
        ->assertJsonPath('data.default_tab', 'result');
    switchTo(ownerOf($draft->entity))->getJson("/api/v1/assessments/{$draft->id}")->assertOk()
        ->assertJsonPath('data.available_tabs', ['request'])
        ->assertJsonPath('data.default_tab', 'request')
        ->assertJsonPath('data.payment.status', 'paid');
    switchTo(ownerOf($self->entity))->getJson("/api/v1/assessments/{$self->id}")->assertOk()
        ->assertJsonPath('data.available_tabs', null)
        ->assertJsonPath('data.period_state', null)
        ->assertJsonPath('data.payment', null);
});

it('hides the payment of an accredited request from its assessor', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk()
        ->assertJsonPath('data.payment', null)
        ->assertJsonPath('data.period.key', '2027-Q1');
});

it('lists the periods for the staff filter', function (): void {
    atRiyadh('2027-02-10 10:00');
    livePeriods();

    switchTo(requestFollower())->getJson('/api/v1/assessment-periods')->assertOk()
        ->assertJsonPath('meta.pagination.total', 5)
        ->assertJsonPath('data.0.key', '2028-Q1')
        ->assertJsonPath('data.0.state', 'upcoming')
        ->assertJsonPath('data.4.key', '2027-Q1')
        ->assertJsonPath('data.4.state', 'current');
    switchTo(systemAdmin())->getJson('/api/v1/assessment-periods?filter[state]=current')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.key', '2027-Q1');
    switchTo(assessorUser())->getJson('/api/v1/assessment-periods?filter[state]=past')->assertOk()->assertJsonPath('data', []);
    switchTo(requestFollower())->getJson('/api/v1/assessment-periods?filter[state]=soon')->assertUnprocessable()->assertJsonValidationErrors('filter.state');
    switchTo(entityUser())->getJson('/api/v1/assessment-periods')->assertForbidden();
    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson('/api/v1/assessment-periods')->assertForbidden();
});

it('filters the request queue by period and shows the period on the queue and the request detail', function (): void {
    atRiyadh('2026-12-20 10:00');
    livePeriods();
    $version = publishedAccreditedCriteria();
    $early = submittedAccreditedRequest($version);
    atRiyadh('2027-02-10 10:00');
    generatePeriods();
    $current = submittedAccreditedRequest($version);
    $follower = requestFollower();
    $q1 = periodByKey('2027-Q1')->id;

    switchTo($follower)->getJson("/api/v1/requests?filter[period_id]={$q1}")->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $current->id)
        ->assertJsonPath('data.0.period_state', 'assigned')
        ->assertJsonPath('data.0.period.key', '2027-Q1');
    switchTo($follower)->getJson('/api/v1/requests?filter[period_id]=none')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $early->id)
        ->assertJsonPath('data.0.period_state', 'pre_periodic')
        ->assertJsonPath('data.0.period_label.ar', 'قبل التقييم الدوري');
    switchTo($follower)->getJson("/api/v1/requests/{$current->id}")->assertOk()
        ->assertJsonPath('data.period.key', '2027-Q1')
        ->assertJsonPath('data.period_label.en', 'Q1 2027');
    switchTo($follower)->getJson('/api/v1/requests?filter[period_id]=0')->assertUnprocessable()->assertJsonValidationErrors('filter.period_id');
    switchTo($follower)->getJson("/api/v1/requests?filter[path]=advanced&filter[period_id]={$q1}")->assertUnprocessable()->assertJsonValidationErrors('filter.period_id');
});
