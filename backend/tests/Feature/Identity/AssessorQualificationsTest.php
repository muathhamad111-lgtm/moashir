<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\UserAssessorQualification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;

const QUALIFICATIONS = '/api/v1/users/%d/assessor-qualifications';

beforeEach(function (): void {
    Notification::fake();
});

it('lets the System Administrator set the paths an assessor is qualified for and audits the change', function (): void {
    $assessor = unqualifiedStaffUser(RoleKey::Assessor);

    switchTo(systemAdmin())->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['advanced_initial', 'accredited']])
        ->assertOk()
        ->assertJsonPath('data.id', $assessor->id)
        ->assertJsonPath('data.assessor_qualifications', ['accredited', 'advanced_initial']);

    $audit = Audit::query()->where('event', AuditEvent::UserAssessorQualificationsChanged->value)->sole();

    expect($audit->old_values)->toBe(['qualifications' => []])
        ->and($audit->new_values)->toBe(['qualifications' => ['accredited', 'advanced_initial']]);

    test()->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['accredited']])
        ->assertOk()
        ->assertJsonPath('data.assessor_qualifications', ['accredited']);

    expect(UserAssessorQualification::query()->where('user_id', $assessor->id)->pluck('qualification')->all())->toBe([AssessorQualification::Accredited])
        ->and(Audit::query()->where('event', AuditEvent::UserAssessorQualificationsChanged->value)->count())->toBe(2);
});

it('does not audit an unchanged set of qualifications', function (): void {
    $assessor = qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::Accredited);

    switchTo(systemAdmin())->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['accredited']])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::UserAssessorQualificationsChanged->value)->count())->toBe(0);
});

it('shows the qualifications and the request scope on the user list and detail', function (): void {
    $assessor = qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::AdvancedInitial);
    $administrator = systemAdmin();

    switchTo($administrator)->getJson('/api/v1/users?filter[role]=assessor')->assertOk()
        ->assertJsonPath('data.0.assessor_qualifications', ['advanced_initial'])
        ->assertJsonPath('data.0.request_scope', ['all_paths' => true, 'paths' => ['accredited', 'advanced']]);
    test()->getJson("/api/v1/users/{$assessor->id}")->assertOk()->assertJsonPath('data.assessor_qualifications', ['advanced_initial']);
});

it('exposes the own qualifications and request scope in the session', function (): void {
    $assessor = qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::Accredited);

    switchTo($assessor)->getJson('/api/v1/auth/session')->assertOk()
        ->assertJsonPath('data.assessor_qualifications', ['accredited'])
        ->assertJsonPath('data.request_scope.all_paths', true);
});

it('requires a reason from the Super Admin and raises the reason alert', function (): void {
    $assessor = unqualifiedStaffUser(RoleKey::Assessor);
    $admin = superAdmin();

    switchTo($admin)->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['accredited']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required');

    test()->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['accredited'], 'reason' => 'تأهيل المقيم لمسار التقييم المعتمد'])
        ->assertOk()
        ->assertJsonPath('data.assessor_qualifications', ['accredited']);

    expect(Audit::query()->where('event', AuditEvent::UserAssessorQualificationsChanged->value)->value('reason'))->toBe('تأهيل المقيم لمسار التقييم المعتمد');
    Notification::assertSentTo($admin, SecurityAlertNotification::class);
});

it('refuses users without the users management permission', function (RoleKey $role): void {
    $assessor = unqualifiedStaffUser(RoleKey::Assessor);

    switchTo(staffUser($role))->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['accredited']])->assertForbidden();

    expect(UserAssessorQualification::query()->where('user_id', $assessor->id)->count())->toBe(0);
})->with([
    'requests follower' => [RoleKey::RequestFollower],
    'assessor' => [RoleKey::Assessor],
    'finance officer' => [RoleKey::FinanceOfficer],
]);

it('refuses entities, guests and hides Super Admin accounts', function (): void {
    $assessor = unqualifiedStaffUser(RoleKey::Assessor);

    test()->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['accredited']])->assertUnauthorized();
    switchTo(ownerOf(entityWithOwner()))->putJson(sprintf(QUALIFICATIONS, $assessor->id), ['qualifications' => ['accredited']])->assertForbidden();
    switchTo(systemAdmin())->putJson(sprintf(QUALIFICATIONS, superAdmin()->id), ['qualifications' => ['accredited']])->assertNotFound();
});

it('refuses invalid qualification sets', function (array $payload, string $field): void {
    $assessor = unqualifiedStaffUser(RoleKey::Assessor);

    switchTo(systemAdmin())->putJson(sprintf(QUALIFICATIONS, $assessor->id), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing list' => [[], 'qualifications'],
    'unknown path' => [['qualifications' => ['leadership']], 'qualifications.0'],
    'self path' => [['qualifications' => ['self']], 'qualifications.0'],
    'duplicate' => [['qualifications' => ['accredited', 'accredited']], 'qualifications.0'],
    'short reason' => [['qualifications' => [], 'reason' => 'قصير'], 'reason'],
]);

it('refuses qualifications for accounts that cannot assess and for the actor himself', function (): void {
    $administrator = staffUser(RoleKey::SystemAdmin, RoleKey::Assessor);
    $follower = staffUser(RoleKey::RequestFollower);

    switchTo($administrator)->putJson(sprintf(QUALIFICATIONS, $follower->id), ['qualifications' => ['accredited']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'assessor_qualification.not_assessor')
        ->assertJsonValidationErrors(['qualifications']);
    test()->putJson(sprintf(QUALIFICATIONS, $follower->id), ['qualifications' => []])->assertOk();
    test()->putJson(sprintf(QUALIFICATIONS, $administrator->id), ['qualifications' => ['accredited']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sod_self_grant');
});
