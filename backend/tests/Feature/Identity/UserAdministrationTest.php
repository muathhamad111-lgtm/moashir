<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountStatusChangedNotification;
use App\Notifications\Identity\MfaResetNotification;
use App\Notifications\Identity\StaffInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

dataset('non-administrators', [
    'criteria officer' => [RoleKey::CriteriaOfficer],
    'requests follower' => [RoleKey::RequestFollower],
    'assessor' => [RoleKey::Assessor],
    'filtering officer' => [RoleKey::FilteringOfficer],
    'award secretary' => [RoleKey::AwardSecretary],
    'content officer' => [RoleKey::ContentOfficer],
    'finance officer' => [RoleKey::FinanceOfficer],
]);

it('lists staff users with pagination meta and statistics', function (): void {
    $administrator = systemAdmin();
    staffUser(RoleKey::Assessor);
    User::factory()->withRole(RoleKey::Assessor)->suspended()->create();
    entityUser();
    signIn($administrator);

    $this->getJson('/api/v1/users?per_page=2&sort=-created_at')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.last_page', 2)
        ->assertJsonPath('meta.stats.total', 3)
        ->assertJsonPath('meta.stats.suspended', 1)
        ->assertJsonPath('meta.stats.by_role.assessor', 2)
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'roles', 'status', 'mfa_enabled', 'last_login_at', 'created_at']]]);
});

it('filters staff users by role, status and search', function (): void {
    signIn(systemAdmin());
    staffUser(RoleKey::FinanceOfficer)->forceFill(['name' => 'ماجد السبيعي'])->save();
    staffUser(RoleKey::Assessor);
    User::factory()->withRole(RoleKey::Assessor)->locked()->create();

    $this->getJson('/api/v1/users?filter[role]=finance_officer')->assertJsonCount(1, 'data')->assertJsonPath('data.0.roles', ['finance_officer']);
    $this->getJson('/api/v1/users?filter[status]=locked')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'locked');
    $this->getJson('/api/v1/users?search='.urlencode('السبيعي'))->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/users?filter[role]=entity')->assertJsonCount(0, 'data');
});

it('validates the list query', function (string $query, string $field): void {
    signIn(systemAdmin());

    $this->getJson('/api/v1/users?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown sort' => ['sort=password', 'sort'],
    'page size above the limit' => ['per_page=500', 'per_page'],
    'unknown role' => ['filter[role]=super_admin', 'filter.role'],
    'unknown filter key' => ['filter[email]=x', 'filter'],
]);

it('denies the users list to every non-administrator role', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'email' => 'x@moashir.example.sa', 'roles' => ['assessor']])->assertForbidden();
})->with('non-administrators');

it('denies the users list to entity users and guests', function (): void {
    $this->getJson('/api/v1/users')->assertUnauthorized();

    signIn(entityUser());
    $this->getJson('/api/v1/users')->assertForbidden();
});

it('invites a staff user with roles and audits the creation', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);

    $this->postJson('/api/v1/users', [
        'name' => 'سلمان المطيري',
        'email' => 'Salman.Almutairi@moashir.example.sa',
        'phone' => '+966551234567',
        'roles' => ['assessor', 'content_officer'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'invited')
        ->assertJsonPath('data.email', 'salman.almutairi@moashir.example.sa')
        ->assertJsonPath('data.roles', ['assessor', 'content_officer']);

    $invitee = User::query()->where('email', 'salman.almutairi@moashir.example.sa')->firstOrFail();
    expect($invitee->mfa_required)->toBeTrue()
        ->and(DB::table('users')->where('id', $invitee->id)->value('phone'))->not->toBe('+966551234567')
        ->and(Audit::query()->where('event', AuditEvent::UserInvited->value)->where('auditable_id', $invitee->id)->exists())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::UserRolesChanged->value)->where('auditable_id', $invitee->id)->value('user_id'))->toBe($administrator->id)
        ->and(DB::table('model_has_roles')->where('model_id', $invitee->id)->value('assigned_by_user_id'))->toBe($administrator->id);
    Notification::assertSentTo($invitee, StaffInvitationNotification::class);
});

it('validates the invitation payload', function (array $payload, string $field): void {
    $existing = systemAdmin();
    signIn($existing);

    $this->postJson('/api/v1/users', [...['name' => 'سلمان المطيري', 'email' => 'new.user@moashir.example.sa', 'roles' => ['assessor']], ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'invalid phone' => [['phone' => '0551234567'], 'phone'],
    'no roles' => [['roles' => []], 'roles'],
    'duplicate roles' => [['roles' => ['assessor', 'assessor']], 'roles.0'],
]);

it('rejects an email that is already registered in any letter case', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);

    $this->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'email' => mb_strtoupper($administrator->email), 'roles' => ['assessor']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('refuses to assign the entity role from the users screen', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'email' => 'new.user@moashir.example.sa', 'roles' => ['entity']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_not_assignable');
});

it('enforces SoD-5: the finance officer and the system administrator roles cannot be combined', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'email' => 'new.user@moashir.example.sa', 'roles' => ['finance_officer', 'system_admin']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sod_role_combination');

    expect(User::query()->where('email', 'new.user@moashir.example.sa')->exists())->toBeFalse();
});

it('enforces SoD-7: an administrator cannot change their own roles', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);

    $this->putJson("/api/v1/users/{$administrator->id}", ['roles' => ['system_admin', 'content_officer']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sod_self_grant');
});

it('enforces SoD-8: an entity account cannot receive a staff role', function (): void {
    signIn(systemAdmin());
    $entity = entityUser();

    $this->putJson("/api/v1/users/{$entity->id}", ['roles' => ['assessor']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sod_role_combination');
});

it('updates a staff user profile and roles with a before and after audit', function (): void {
    signIn(systemAdmin());
    $target = staffUser(RoleKey::Assessor);

    $this->putJson("/api/v1/users/{$target->id}", ['name' => 'عبدالله الدوسري', 'roles' => ['assessor', 'filtering_officer']])
        ->assertOk()
        ->assertJsonPath('data.name', 'عبدالله الدوسري')
        ->assertJsonPath('data.roles', ['assessor', 'filtering_officer']);

    $audit = Audit::query()->where('event', AuditEvent::UserRolesChanged->value)->where('auditable_id', $target->id)->latest('id')->firstOrFail();
    expect($audit->old_values)->toBe(['roles' => ['assessor']])
        ->and($audit->new_values)->toBe(['roles' => ['assessor', 'filtering_officer']]);
});

it('ignores attempts to set protected attributes through the update payload', function (): void {
    signIn(systemAdmin());
    $target = staffUser(RoleKey::Assessor);

    $this->putJson("/api/v1/users/{$target->id}", ['name' => 'عبدالله الدوسري', 'is_super_admin' => true, 'status' => 'active', 'mfa_required' => false])->assertOk();

    expect($target->refresh()->isSuperAdmin())->toBeFalse()
        ->and($target->mfa_required)->toBeTrue();
});

it('suspends a staff user with a reason, ends the sessions and notifies the user', function (): void {
    signIn(systemAdmin());
    $target = staffUser(RoleKey::Assessor);
    DB::table('sessions')->insert(['id' => 'assessor-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

    $this->postJson("/api/v1/users/{$target->id}/suspend", ['reason' => 'انتهاء التكليف بنهاية دورة التقييم الحالية'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::UserSuspended->value)->value('reason'))->toBe('انتهاء التكليف بنهاية دورة التقييم الحالية');
    Notification::assertSentTo($target, AccountStatusChangedNotification::class);
});

it('requires a reason of at least ten characters to suspend', function (): void {
    signIn(systemAdmin());
    $target = staffUser(RoleKey::Assessor);

    $this->postJson("/api/v1/users/{$target->id}/suspend", ['reason' => 'قصير'])->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('refuses illegal status transitions with 409', function (): void {
    signIn(systemAdmin());
    $suspended = User::factory()->withRole(RoleKey::Assessor)->suspended()->create();
    $active = staffUser(RoleKey::Assessor);

    $this->postJson("/api/v1/users/{$suspended->id}/suspend", ['reason' => 'سبب إداري موثق للإيقاف'])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal')
        ->assertJsonPath('errors.attempted.0', 'suspend');
    $this->postJson("/api/v1/users/{$active->id}/activate")->assertConflict();
    $this->postJson("/api/v1/users/{$active->id}/unlock")->assertConflict();
});

it('refuses to suspend the own account or an entity account', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);

    $this->postJson("/api/v1/users/{$administrator->id}/suspend", ['reason' => 'سبب إداري موثق للإيقاف'])->assertUnprocessable()->assertJsonPath('code', 'user.self');
    $this->postJson('/api/v1/users/'.entityUser()->id.'/suspend', ['reason' => 'سبب إداري موثق للإيقاف'])->assertUnprocessable()->assertJsonPath('code', 'user.entity_status_managed');
});

it('reactivates a suspended staff user', function (): void {
    signIn(systemAdmin());
    $target = User::factory()->withRole(RoleKey::Assessor)->suspended()->create();

    $this->postJson("/api/v1/users/{$target->id}/activate")->assertOk()->assertJsonPath('data.status', 'active');
    expect($target->refresh()->status)->toBe(UserStatus::Active);
});

it('unlocks a locked account before the lockout ends', function (): void {
    signIn(systemAdmin());
    $target = User::factory()->withRole(RoleKey::Entity)->locked()->create();

    $this->postJson("/api/v1/users/{$target->id}/unlock", ['reason' => 'تحقق هاتفي من هوية المستخدم'])->assertOk()->assertJsonPath('data.status', 'active');
    expect($target->refresh()->isLocked())->toBeFalse();
});

it('resets the TOTP of a staff user with a reason', function (): void {
    signIn(systemAdmin());
    $target = staffUser(RoleKey::Assessor);

    $this->postJson("/api/v1/users/{$target->id}/mfa/reset", ['reason' => 'فقدان جهاز المصادقة المسجل'])
        ->assertOk()
        ->assertJsonPath('data.mfa_enabled', false);

    expect($target->refresh()->hasConfirmedTotp())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::UserMfaReset->value)->value('reason'))->toBe('فقدان جهاز المصادقة المسجل');
    Notification::assertSentTo($target, MfaResetNotification::class);
    $this->postJson("/api/v1/users/{$target->id}/mfa/reset", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('denies the status and MFA endpoints to non-administrators', function (RoleKey $role): void {
    signIn(staffUser($role));
    $target = staffUser(RoleKey::Assessor);

    $this->postJson("/api/v1/users/{$target->id}/suspend", ['reason' => 'سبب إداري موثق للإيقاف'])->assertForbidden();
    $this->postJson("/api/v1/users/{$target->id}/mfa/reset", ['reason' => 'سبب إداري موثق للإيقاف'])->assertForbidden();
    $this->putJson("/api/v1/users/{$target->id}", ['name' => 'اسم جديد'])->assertForbidden();
    $this->getJson("/api/v1/users/{$target->id}")->assertForbidden();
})->with('non-administrators');

it('lists the assignable roles with bilingual labels for user pickers', function (): void {
    signIn(systemAdmin());

    $roles = $this->getJson('/api/v1/roles')->assertOk()->json('data');

    expect(collect($roles)->pluck('key')->sort()->values()->all())->toBe(collect(RoleKey::values())->sort()->values()->all())
        ->and(collect($roles)->firstWhere('key', 'entity')['assignable'])->toBeFalse()
        ->and(collect($roles)->firstWhere('key', 'assessor')['label'])->toBe(['ar' => 'المقيّم', 'en' => 'Assessor']);
});

it('denies the roles list to users without users or matrix permissions', function (): void {
    signIn(staffUser(RoleKey::Assessor));

    $this->getJson('/api/v1/roles')->assertForbidden();
});

it('searches the users list with the same Arabic-aware matching', function (): void {
    $admin = systemAdmin();
    $admin->forceFill(['name' => 'خالد العنزي'])->save();
    signIn($admin);
    staffUser(RoleKey::Assessor)->forceFill(['name' => 'إيمان الشمري'])->save();

    $this->getJson('/api/v1/users?search='.urlencode('ايمان'))->assertOk()->assertJsonCount(1, 'data');
});

it('ignores a users list search with nothing searchable in it', function (): void {
    signIn(systemAdmin());
    staffUser(RoleKey::Assessor);

    $this->getJson('/api/v1/users?search='.urlencode('ـــ'))->assertOk()->assertJsonCount(2, 'data');
});
