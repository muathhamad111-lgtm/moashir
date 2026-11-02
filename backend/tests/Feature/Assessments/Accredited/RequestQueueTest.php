<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lists the submitted accredited requests of operational entities with SLA data and stats', function (): void {
    $version = publishedAccreditedCriteria();
    openedAccreditedRequest();
    $submitted = submittedAccreditedRequest($version);
    $frozen = submittedAccreditedRequest($version);
    $frozen->entity->delete();
    $overdue = submittedAccreditedRequest($version);
    $overdue->forceFill(['due_at' => Date::now()->subDay()])->save();

    $response = switchTo(requestFollower())->getJson('/api/v1/requests')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.stats.total', 2)
        ->assertJsonPath('meta.stats.by_status.submitted', 2)
        ->assertJsonPath('meta.stats.overdue', 1);

    expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())->toBe(collect([$submitted->id, $overdue->id])->sort()->values()->all());

    test()->getJson('/api/v1/requests?filter[overdue]=true')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.is_overdue', true);
    test()->getJson('/api/v1/requests?filter[overdue]=false')->assertOk()->assertJsonPath('data.0.id', $submitted->id);
    test()->getJson('/api/v1/requests?search='.$submitted->request_number)->assertOk()->assertJsonPath('meta.pagination.total', 1);
    test()->getJson('/api/v1/requests?filter[status]=draft')->assertUnprocessable();
    test()->getJson('/api/v1/requests?sort=-entity')->assertUnprocessable();
});

it('keeps the request queue to the Requests Follower and the System Administrator', function (RoleKey $role, int $status): void {
    switchTo(staffUser($role))->getJson('/api/v1/requests')->assertStatus($status);
})->with([
    'system administrator' => [RoleKey::SystemAdmin, 200],
    'assessor' => [RoleKey::Assessor, 403],
    'finance officer' => [RoleKey::FinanceOfficer, 403],
]);

it('refuses the request queue to entities', function (): void {
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/requests')->assertForbidden();
});

it('lists eligible assessors without the actor, Super Admins or inactive accounts', function (): void {
    $version = publishedAccreditedCriteria();
    $active = assessorUser();
    $busy = assessorUser();
    assignedAccreditedRequest($version, $busy);
    $suspended = assessorUser();
    $suspended->forceFill(['status' => UserStatus::Suspended])->save();
    $followerAssessor = staffUser(RoleKey::RequestFollower, RoleKey::Assessor);

    $response = switchTo($followerAssessor)->getJson('/api/v1/requests/assessors')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($active->id, $busy->id)
        ->and($ids)->not->toContain($suspended->id, $followerAssessor->id)
        ->and(collect($response->json('data'))->firstWhere('id', $busy->id)['open_requests'])->toBe(1);

    switchTo(assessorUser())->getJson('/api/v1/requests/assessors')->assertForbidden();
});

it('assigns a submitted request to an assessor and notifies the assessor and the followers', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    $assessor = assessorUser();
    $follower = requestFollower();
    $otherFollower = requestFollower();

    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])->assertOk()
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.assessor.id', $assessor->id)
        ->assertJsonPath('data.assignment_history.0.assigned_by.id', $follower->id)
        ->assertJsonPath('data.payment.paid', true);

    expect(AssessmentAssignment::query()->where('assessment_id', $assessment->id)->active()->value('assessor_user_id'))->toBe($assessor->id)
        ->and(Audit::query()->where('event', AuditEvent::AssessmentAssigned->value)->exists())->toBeTrue()
        ->and(financeNoticeSent($assessor, NotificationCode::AccreditedAssigned))->toBeTrue()
        ->and(financeNoticeSent($otherFollower, NotificationCode::AccreditedAssigned))->toBeTrue();

    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('refuses invalid assignments', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    $followerAssessor = staffUser(RoleKey::RequestFollower, RoleKey::Assessor);
    $draft = openedAccreditedRequest();

    switchTo($followerAssessor)->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $followerAssessor->id])
        ->assertUnprocessable()->assertJsonPath('code', 'sod_self_assignment');
    test()->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => requestFollower()->id])
        ->assertUnprocessable()->assertJsonPath('code', 'assessor.invalid');
    test()->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => superAdmin()->id])
        ->assertUnprocessable()->assertJsonPath('code', 'assessor.invalid');
    test()->postJson("/api/v1/requests/{$assessment->id}/assign", [])->assertUnprocessable()->assertJsonValidationErrors(['assessor_id']);
    test()->postJson("/api/v1/requests/{$draft->id}/assign", ['assessor_id' => assessorUser()->id])->assertConflict();
    switchTo(assessorUser())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => assessorUser()->id])->assertForbidden();
    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => assessorUser()->id])->assertForbidden();
});

it('refuses to assign the request of a deleted entity', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    Entity::query()->whereKey($assessment->entity_id)->firstOrFail()->delete();

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => assessorUser()->id])
        ->assertConflict()->assertJsonPath('code', 'entity.deleted');
});

it('requires a reason from the Super Admin to assign a request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    $admin = superAdmin();
    $assessor = assessorUser();

    switchTo($admin)->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    switchTo($admin)->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id, 'reason' => SUPER_ADMIN_REASON])->assertOk();

    Notification::assertSentTo($admin, SecurityAlertNotification::class);
});

it('reassigns a request with a reason, keeps the history and removes the previous assessor', function (): void {
    $version = publishedAccreditedCriteria();
    $first = assessorUser();
    $second = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $first);
    $follower = requestFollower();

    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => $second->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    test()->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => $first->id, 'reason' => ASSIGNMENT_REASON])
        ->assertUnprocessable()->assertJsonPath('code', 'assessor.same');

    $response = test()->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => $second->id, 'reason' => ASSIGNMENT_REASON])->assertOk()
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.assessor.id', $second->id);

    expect(collect($response->json('data.assignment_history'))->pluck('status')->all())->toBe(['reassigned', 'active'])
        ->and($response->json('data.assignment_history.0.end_reason'))->toBe(ASSIGNMENT_REASON)
        ->and(financeNoticeSent($first, NotificationCode::AccreditedAssigned))->toBeTrue();

    switchTo($first)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertNotFound();
    switchTo($second)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.mode', 'edit');
});

it('refuses to reassign a request that has no assessor yet', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);

    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/reassign", ['assessor_id' => assessorUser()->id, 'reason' => ASSIGNMENT_REASON])
        ->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
});

it('shows the request detail to the followers and a narrow view to the assigned assessor', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);

    $staff = switchTo(systemAdmin())->getJson("/api/v1/requests/{$assessment->id}")->assertOk();
    expect($staff->json('data.assignment_history.0'))->toHaveKeys(['assigned_by', 'reason'])
        ->and($staff->json('data.record.status_history.3'))->toHaveKey('actor_staff');

    $own = switchTo($assessor)->getJson("/api/v1/requests/{$assessment->id}")->assertOk();
    expect($own->json('data.assignment_history.0'))->not->toHaveKeys(['assigned_by', 'reason'])
        ->and($own->json('data.record.status_history.3'))->not->toHaveKey('actor_staff');

    switchTo(assessorUser())->getJson("/api/v1/requests/{$assessment->id}")->assertNotFound();
    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/requests/{$assessment->id}")->assertForbidden();
});
