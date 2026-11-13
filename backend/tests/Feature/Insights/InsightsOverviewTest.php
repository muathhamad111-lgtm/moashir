<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

const OVERVIEW_MARKS = ['M1.1' => '70.00', 'M1.2' => '70.00', 'M2.1' => '70.00', 'M2.2' => '70.00'];

beforeEach(function (): void {
    Notification::fake();
});

it('selects the latest eligible accredited result and reports the rules versions', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    accreditedResultFor($entity, $version, OVERVIEW_MARKS, '2026-01-01 09:00:00');
    $latest = accreditedResultFor($entity, $version, OVERVIEW_MARKS, '2026-06-01 09:00:00');

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected.assessment_id', $latest->id)
        ->assertJsonPath('data.selected.percent', '70.00')
        ->assertJsonPath('data.selected.verified_at', '2026-06-01T09:00:00+00:00')
        ->assertJsonPath('data.exclusions', [])
        ->assertJsonPath('data.empty_state', null)
        ->assertJsonPath('data.in_progress_request', null)
        ->assertJsonPath('data.rules.gap_thresholds_version', 1)
        ->assertJsonPath('data.rules.maturity_bands_version', 1);
});

it('falls back to the previous result with a notice when the latest certificate is revoked', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $previous = accreditedResultFor($entity, $version, OVERVIEW_MARKS, '2026-01-01 09:00:00');
    $revoked = accreditedResultFor($entity, $version, OVERVIEW_MARKS, '2026-06-01 09:00:00');
    revokeResultCertificate($revoked);

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected.assessment_id', $previous->id)
        ->assertJsonPath('data.exclusions.0.assessment_id', $revoked->id)
        ->assertJsonPath('data.exclusions.0.number', $revoked->request_number)
        ->assertJsonPath('data.exclusions.0.reason', 'certificate_revoked');
});

it('answers 200 with the empty state that matches the entity', function (string $case, string $reason, ?string $cta): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $open = null;

    match ($case) {
        'nothing' => null,
        'only self' => selfResultFor($entity, $version),
        'in progress' => $open = insightsAssessment($entity, $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted),
        'under review' => revokeResultCertificate(accreditedResultFor($entity, $version, OVERVIEW_MARKS)),
    };

    $response = switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected', null)
        ->assertJsonPath('data.empty_state.reason', $reason)
        ->assertJsonPath('data.empty_state.cta.kind', $cta);

    if ($open instanceof Assessment) {
        $response->assertJsonPath('data.empty_state.cta.assessment_id', $open->id)
            ->assertJsonPath('data.in_progress_request.assessment_id', $open->id);
    }
})->with([
    'no result' => ['nothing', 'no_verified_result', 'start_accredited'],
    'self only' => ['only self', 'only_self', 'start_accredited'],
    'request open' => ['in progress', 'in_progress', 'view_request'],
    'excluded only' => ['under review', 'under_review', null],
]);

it('keeps the previous result selected while a new accredited request is open', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $completed = accreditedResultFor($entity, $version, OVERVIEW_MARKS);
    $open = insightsAssessment($entity, $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected.assessment_id', $completed->id)
        ->assertJsonPath('data.in_progress_request.assessment_id', $open->id);
});

it('lists the eligible accredited results newest first, paginated, without self or excluded results', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $ids = [];

    foreach (range(1, 16) as $month) {
        $ids[] = accreditedResultFor($entity, $version, OVERVIEW_MARKS, CarbonImmutable::parse('2025-01-01 09:00:00')->addMonths($month)->toDateTimeString())->id;
    }

    revokeResultCertificate(accreditedResultFor($entity, $version, OVERVIEW_MARKS, '2026-09-01 09:00:00'));
    selfResultFor($entity, $version);
    $owner = ownerOf($entity);

    switchTo($owner)->getJson('/api/v1/entities/me/insights/results')->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.pagination.total', 16)
        ->assertJsonPath('meta.pagination.last_page', 2)
        ->assertJsonPath('data.0.assessment_id', $ids[15]);
    switchTo($owner)->getJson('/api/v1/entities/me/insights/results?page=2')->assertOk()->assertJsonCount(1, 'data');
    switchTo($owner)->getJson('/api/v1/entities/me/insights/results?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('shows the staff the same overview read-only, path-scoped and without call to action', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $result = accreditedResultFor($entity, $version, OVERVIEW_MARKS);
    $empty = entityWithOwner();

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/insights")->assertOk()->assertJsonPath('data.selected.assessment_id', $result->id);
    switchTo(requestFollower())->getJson("/api/v1/entities/{$entity->id}/insights/results")->assertOk()->assertJsonCount(1, 'data');
    switchTo(systemAdmin())->getJson("/api/v1/entities/{$empty->id}/insights")->assertOk()
        ->assertJsonPath('data.empty_state.reason', 'no_verified_result')
        ->assertJsonPath('data.empty_state.cta', null);
    switchTo(followerOfPaths(AssessmentPath::Advanced))->getJson("/api/v1/entities/{$entity->id}/insights")->assertOk()
        ->assertJsonPath('data.selected', null)
        ->assertJsonPath('data.empty_state.reason', 'no_verified_result');
    switchTo(followerOfPaths(AssessmentPath::Advanced))->getJson("/api/v1/entities/{$entity->id}/insights/results")->assertOk()->assertJsonCount(0, 'data');
});

it('denies the overview to roles without the insights permissions', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->getJson("/api/v1/entities/{$entity->id}/insights")->assertForbidden();
    switchTo(assessorUser())->getJson("/api/v1/entities/{$entity->id}/insights")->assertForbidden();
    switchTo(staffUser(RoleKey::ContentOfficer))->getJson("/api/v1/entities/{$entity->id}/report-library")->assertForbidden();
    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson('/api/v1/entities/me/insights')->assertForbidden();
    switchTo(systemAdmin())->getJson('/api/v1/entities/me/insights')->assertForbidden();
    switchTo(systemAdmin())->getJson('/api/v1/entities/999999/insights')->assertNotFound();
});

it('hides a deleted entity from staff without the delete permission and keeps it for the System Administrator', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    accreditedResultFor($entity, $version, OVERVIEW_MARKS);
    $entity->delete();

    switchTo(requestFollower())->getJson("/api/v1/entities/{$entity->id}/insights")->assertNotFound();
    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/insights")->assertOk()->assertJsonPath('data.selected.percent', '70.00');
});

it('lists the report library per accredited result with the availability of every document', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $certified = accreditedResultFor($entity, $version, OVERVIEW_MARKS, '2026-01-01 09:00:00');
    $certificate = certifyResult($certified);
    $revoked = accreditedResultFor($entity, $version, OVERVIEW_MARKS, '2026-06-01 09:00:00');
    revokeResultCertificate($revoked);
    selfResultFor($entity, $version);

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/report-library')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.coming_soon', [['kind' => 'periodic'], ['kind' => 'annual'], ['kind' => 'benchmark']])
        ->assertJsonPath('data.0.result.assessment_id', $revoked->id)
        ->assertJsonPath('data.0.eligible', false)
        ->assertJsonPath('data.0.exclusion_reason', 'certificate_revoked')
        ->assertJsonPath('data.0.items.0.kind', 'feedback_report')
        ->assertJsonPath('data.0.items.0.available', true)
        ->assertJsonPath('data.0.items.1.available', false)
        ->assertJsonPath('data.0.items.2.status', 'revoked')
        ->assertJsonPath('data.0.items.2.available', false)
        ->assertJsonPath('data.1.eligible', true)
        ->assertJsonPath('data.1.items.1.available', true)
        ->assertJsonPath('data.1.items.2.available', true)
        ->assertJsonPath('data.1.items.2.certificate_id', $certificate->public_id);
    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/report-library")->assertOk()->assertJsonCount(2, 'data');
});
