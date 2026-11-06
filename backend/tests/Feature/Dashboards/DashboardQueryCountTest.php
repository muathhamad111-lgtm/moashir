<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Finance\Payment;
use App\Models\Kpis\Kpi;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const MAX_DASHBOARD_QUERIES = 45;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-03 08:00:00');
});

it('builds the global boards with a constant number of queries', function (): void {
    $admin = systemAdmin();
    $finance = staffUser(RoleKey::FinanceOfficer);
    $content = contentOfficer();
    storedPaidPayment();
    Kpi::factory()->create();
    $before = [dashboardQueryCount($admin, 'system_admin'), dashboardQueryCount($admin, 'executive'), dashboardQueryCount($finance, 'finance_officer'), dashboardQueryCount($content, 'content_officer')];

    for ($index = 0; $index < 4; $index++) {
        storedPaidPayment(null, $index % 2 === 0 ? IntegrationMode::Live : IntegrationMode::Test);
        Payment::factory()->create();
        Kpi::factory()->create();
        entityWithOwner(static fn ($factory) => $factory->pendingVerification());
    }

    $after = [dashboardQueryCount($admin, 'system_admin'), dashboardQueryCount($admin, 'executive'), dashboardQueryCount($finance, 'finance_officer'), dashboardQueryCount($content, 'content_officer')];

    expect($after)->toBe($before)->and(max($after))->toBeLessThanOrEqual(MAX_DASHBOARD_QUERIES);
});

it('builds the request boards with a constant number of queries', function (): void {
    $version = publishedAccreditedCriteria();
    $follower = requestFollower();
    $assessor = assessorUser();
    completedAccreditedRequest($version, $assessor);
    reviewedAccreditedRequest($version, $assessor);
    $before = [dashboardQueryCount($follower, 'request_follower'), dashboardQueryCount($assessor, 'assessor')];

    completedAccreditedRequest($version, $assessor);
    reviewedAccreditedRequest($version, $assessor);
    assignedAccreditedRequest($version, $assessor);
    submittedAccreditedRequest($version);

    $after = [dashboardQueryCount($follower, 'request_follower'), dashboardQueryCount($assessor, 'assessor')];

    expect($after)->toBe($before)->and(max($after))->toBeLessThanOrEqual(MAX_DASHBOARD_QUERIES);
});

it('builds the entity board with a constant number of queries', function (): void {
    $version = publishedAccreditedCriteria();
    $completed = completedAccreditedRequest($version, assessorUser());
    $entity = $completed->entity;
    $owner = ownerOf($entity);
    $before = dashboardQueryCount($owner, 'entity');

    storedPaidPayment($entity);
    storedPaidPayment($entity);
    Payment::factory()->forEntity($entity)->create();
    inAppNotification($owner);

    expect(dashboardQueryCount($owner, 'entity'))->toBe($before)->and($before)->toBeLessThanOrEqual(MAX_DASHBOARD_QUERIES);
});

it('builds the award boards with a constant number of queries', function (): void {
    $scenario = committeeScenario(2, 1);
    [$seated] = $scenario['assessors'];
    [$first, $second] = $scenario['requests'];
    submitCommitteeEvaluation($seated, $first, $scenario['version'])->assertOk();
    $before = [
        dashboardQueryCount($scenario['secretary'], 'award_secretary'),
        dashboardQueryCount($scenario['officer'], 'filtering_officer'),
        dashboardQueryCount($seated, 'committee_assessor'),
    ];

    switchTo($scenario['secretary'])->postJson(secretaryPath($scenario['cycle'], $first, 'approve'))->assertOk();
    submitCommitteeEvaluation($seated, $second, $scenario['version'])->assertOk();

    $after = [
        dashboardQueryCount($scenario['secretary'], 'award_secretary'),
        dashboardQueryCount($scenario['officer'], 'filtering_officer'),
        dashboardQueryCount($seated, 'committee_assessor'),
    ];

    expect($after)->toBe($before)->and(max($after))->toBeLessThanOrEqual(MAX_DASHBOARD_QUERIES);
});
