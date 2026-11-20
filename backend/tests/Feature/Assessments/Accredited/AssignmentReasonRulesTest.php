<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

// Admin review 2026-11-20 (R3): two general settings decide whether assigning and reassigning need a reason.
const ASSIGNMENT_REASON_RULES = '/api/v1/settings/assessment/assignment-reasons';

beforeEach(function (): void {
    Notification::fake();
});

it('keeps the assign reason optional and the reassign reason required by default', function (): void {
    switchTo(systemAdmin())->getJson(ASSIGNMENT_REASON_RULES)->assertOk()
        ->assertJsonPath('data', ['assign_required' => false, 'reassign_required' => true]);
});

it('saves both switches, audits the change and returns the new rules', function (): void {
    switchTo(systemAdmin())->putJson(ASSIGNMENT_REASON_RULES, ['assign_required' => true, 'reassign_required' => false])->assertOk()
        ->assertJsonPath('data.assign_required', true)
        ->assertJsonPath('data.reassign_required', false);

    test()->getJson(ASSIGNMENT_REASON_RULES)->assertOk()->assertJsonPath('data.assign_required', true);
    expect(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->count())->toBe(2);
});

it('requires an assign reason when the switch is on', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    $assessor = assessorUser();
    switchTo(systemAdmin())->putJson(ASSIGNMENT_REASON_RULES, ['assign_required' => true, 'reassign_required' => true])->assertOk();

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    test()->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id, 'reason' => 'قصير'])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    test()->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id, 'reason' => ASSIGNMENT_REASON])->assertOk()
        ->assertJsonPath('data.assignment_history.0.reason', ASSIGNMENT_REASON);
});

it('lets a follower reassign without a reason when the switch is off', function (): void {
    $version = publishedAccreditedCriteria();
    $first = assessorUser();
    $second = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $first);
    switchTo(systemAdmin())->putJson(ASSIGNMENT_REASON_RULES, ['assign_required' => false, 'reassign_required' => false])->assertOk();

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => $second->id])->assertOk()
        ->assertJsonPath('data.assessor.id', $second->id);

    expect(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->orderBy('id')->pluck('end_reason')->all())->toBe([null, null]);
});

it('still asks the Super Admin for a reason whatever the switches say', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    $admin = superAdmin();
    switchTo(systemAdmin())->putJson(ASSIGNMENT_REASON_RULES, ['assign_required' => false, 'reassign_required' => false])->assertOk();

    switchTo($admin)->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => assessorUser()->id])
        ->assertUnprocessable()->assertJsonPath('code', 'reason_required');
});

it('sends the reason rules with the eligible assessors', function (): void {
    publishedAccreditedCriteria();
    switchTo(systemAdmin())->putJson(ASSIGNMENT_REASON_RULES, ['assign_required' => true, 'reassign_required' => false])->assertOk();

    switchTo(requestFollower())->getJson('/api/v1/requests/assessors?path=accredited')->assertOk()
        ->assertJsonPath('meta.reason_rules', ['assign_required' => true, 'reassign_required' => false]);
});

it('keeps the reason rules to the assessment settings managers', function (RoleKey $role, int $status): void {
    switchTo(staffUser($role))->getJson(ASSIGNMENT_REASON_RULES)->assertStatus($status);
    test()->putJson(ASSIGNMENT_REASON_RULES, ['assign_required' => true, 'reassign_required' => true])->assertStatus($status);
})->with([
    'system administrator' => [RoleKey::SystemAdmin, 200],
    'requests follower' => [RoleKey::RequestFollower, 403],
    'assessor' => [RoleKey::Assessor, 403],
]);

it('validates the reason rules', function (array $payload): void {
    switchTo(systemAdmin())->putJson(ASSIGNMENT_REASON_RULES, $payload)->assertUnprocessable();
})->with([
    'missing switches' => [[]],
    'not a boolean' => [['assign_required' => 'sometimes', 'reassign_required' => true]],
    'short reason' => [['assign_required' => true, 'reassign_required' => true, 'reason' => 'قصير']],
]);

it('drops the reassign reason check and brings it back for new rows only', function (): void {
    $check = static fn (): int => count(DB::select("select 1 from pg_constraint where conname = 'assessment_assignments_reassign_reason_check'"));
    $migration = require database_path('migrations/2026_11_20_210000_allow_reassignment_without_reason.php');

    expect($check())->toBe(0);
    $migration->down();
    expect($check())->toBe(1);
    $migration->up();
    expect($check())->toBe(0);
});
