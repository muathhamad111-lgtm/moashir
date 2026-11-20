<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Audit\Audit;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Models\Identity\UserIdentityChange;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

const MY_IDENTITY_CHANGES = '/api/v1/me/identity-changes';

const IDENTITY_CHANGES = '/api/v1/identity-changes';

const CHANGE_REASON = 'تصحيح خطأ إملائي في الاسم المسجل';

beforeEach(function (): void {
    Notification::fake();
});

function submitOwnChange(User $user, array $payload): TestResponse
{
    return switchTo($user)->postJson(MY_IDENTITY_CHANGES, ['reason' => CHANGE_REASON, ...$payload]);
}

function pendingChangeOf(User $user, array $payload = ['new_name' => 'سلمان بن فهد المطيري']): IdentityChangeRequest
{
    submitOwnChange($user, $payload)->assertCreated();

    return IdentityChangeRequest::query()->where('user_id', $user->id)->sole();
}

function noticeCodeIs(NotificationCode $code): Closure
{
    return static fn (EntityNotification $notification): bool => $notification->notice->code === $code;
}

it('lets a user ask to change their name and notifies the reviewers in-app', function (): void {
    $reviewer = staffUser(RoleKey::SystemAdmin);
    $user = staffUser(RoleKey::Assessor);

    submitOwnChange($user, ['new_name' => 'سلمان بن فهد المطيري'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.fields', ['name'])
        ->assertJsonPath('data.requested_name', 'سلمان بن فهد المطيري');

    expect($user->refresh()->name)->not->toBe('سلمان بن فهد المطيري');
    Notification::assertSentTo($reviewer, EntityNotification::class, noticeCodeIs(NotificationCode::IdentityChangeRequested));
    Notification::assertNotSentTo($user, EntityNotification::class);
});

it('requires the current password to ask for a national ID change', function (): void {
    $user = giveNationalId(staffUser(RoleKey::Assessor), 11);

    submitOwnChange($user, ['new_national_id' => fictionalNationalId(12)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
    submitOwnChange($user, ['new_national_id' => fictionalNationalId(12), 'current_password' => 'Not-The-Password-2026'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    submitOwnChange($user, ['new_national_id' => fictionalNationalId(12), 'current_password' => defaultPassword()])
        ->assertCreated()
        ->assertJsonPath('data.requested_national_id_masked', '••••••'.substr(fictionalNationalId(12), -4));
});

it('refuses an empty, unchanged, invalid or duplicate request', function (): void {
    giveNationalId(staffUser(RoleKey::Assessor), 21);
    $user = staffUser(RoleKey::Assessor);

    submitOwnChange($user, [])->assertUnprocessable()->assertJsonValidationErrors(['new_name', 'new_national_id']);
    submitOwnChange($user, ['new_name' => $user->name])->assertUnprocessable()->assertJsonPath('code', 'identity_change.nothing_to_change');
    submitOwnChange($user, ['new_national_id' => '1000000009', 'current_password' => defaultPassword()])->assertUnprocessable()->assertJsonValidationErrors('new_national_id');
    submitOwnChange($user, ['new_national_id' => fictionalNationalId(21), 'current_password' => defaultPassword()])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'identity.national_id_unavailable');
    submitOwnChange($user, ['new_name' => 'اسم جديد', 'reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('keeps one open request per user', function (): void {
    $user = staffUser(RoleKey::Assessor);
    pendingChangeOf($user);

    submitOwnChange($user, ['new_name' => 'اسم آخر مختلف'])->assertConflict()->assertJsonPath('code', 'identity_change.already_pending');
});

it('lets only the requester cancel a pending request', function (): void {
    $user = staffUser(RoleKey::Assessor);
    $change = pendingChangeOf($user);

    switchTo(staffUser(RoleKey::Assessor))->postJson(MY_IDENTITY_CHANGES."/{$change->public_id}/cancel")->assertNotFound();
    switchTo($user)->postJson(MY_IDENTITY_CHANGES."/{$change->public_id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    switchTo($user)->postJson(MY_IDENTITY_CHANGES."/{$change->public_id}/cancel")->assertConflict();
});

it('applies an approved change, keeps an encrypted history and notifies the requester', function (): void {
    $user = giveNationalId(staffUser(RoleKey::Assessor), 31);
    $oldName = $user->name;
    $change = pendingChangeOf($user, ['new_name' => 'سلمان بن فهد المطيري', 'new_national_id' => fictionalNationalId(32), 'current_password' => defaultPassword()]);

    switchTo(systemAdmin())->postJson(IDENTITY_CHANGES."/{$change->public_id}/approve", ['note' => 'تم التحقق من خطاب جهة العمل'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $user->refresh();
    expect($user->name)->toBe('سلمان بن فهد المطيري')
        ->and($user->national_id)->toBe(fictionalNationalId(32));
    $history = UserIdentityChange::query()->where('user_id', $user->id)->orderBy('id')->get();
    expect($history->pluck('field')->map->value->all())->toBe(['name', 'national_id'])
        ->and($history[0]->old_value)->toBe($oldName)
        ->and($history[1]->old_value)->toBe(fictionalNationalId(31))
        ->and(DB::table('user_identity_changes')->pluck('new_value')->implode(' '))->not->toContain(fictionalNationalId(32));
    Notification::assertSentTo($user, EntityNotification::class, noticeCodeIs(NotificationCode::IdentityChangeDecided));
    expect(Audit::query()->where('event', AuditEvent::IdentityChangeApproved->value)->sole()->new_values['fields'])->toBe(['name', 'national_id']);

    switchTo(systemAdmin())->postJson(IDENTITY_CHANGES."/{$change->public_id}/approve")->assertConflict();
});

it('rejects a change only with a note and leaves the account unchanged', function (): void {
    $user = staffUser(RoleKey::Assessor);
    $name = $user->name;
    $change = pendingChangeOf($user);
    $reviewer = systemAdmin();

    switchTo($reviewer)->postJson(IDENTITY_CHANGES."/{$change->public_id}/reject")->assertUnprocessable()->assertJsonValidationErrors('note');
    switchTo($reviewer)->postJson(IDENTITY_CHANGES."/{$change->public_id}/reject", ['note' => 'لا يطابق الاسم الوثائق الرسمية'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', 'لا يطابق الاسم الوثائق الرسمية');

    expect($user->refresh()->name)->toBe($name)
        ->and(UserIdentityChange::query()->count())->toBe(0);
    Notification::assertSentTo($user, EntityNotification::class, noticeCodeIs(NotificationCode::IdentityChangeDecided));
});

it('never lets a reviewer decide their own request, the Super Admin included', function (): void {
    $reviewer = systemAdmin();
    $change = pendingChangeOf($reviewer);

    switchTo($reviewer)->postJson(IDENTITY_CHANGES."/{$change->public_id}/approve")->assertForbidden();

    $superAdmin = superAdmin();
    $own = pendingChangeOf($superAdmin);
    switchTo($superAdmin)->postJson(IDENTITY_CHANGES."/{$own->public_id}/approve")->assertForbidden()->assertJsonPath('code', 'identity_change.own_request');
});

it('keeps the review queue to holders of the review permission, which can be granted to another role', function (): void {
    $change = pendingChangeOf(staffUser(RoleKey::Assessor));
    $finance = staffUser(RoleKey::FinanceOfficer);

    switchTo($finance)->getJson(IDENTITY_CHANGES)->assertForbidden();
    switchTo($finance)->getJson(IDENTITY_CHANGES."/{$change->public_id}")->assertForbidden();
    switchTo($finance)->postJson(IDENTITY_CHANGES."/{$change->public_id}/approve")->assertForbidden();

    Role::findByName(RoleKey::FinanceOfficer->value, 'web')->givePermissionTo(PermissionKey::UsersIdentityChangesReview->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    switchTo($finance->refresh())->getJson(IDENTITY_CHANGES)->assertOk()->assertJsonPath('data.0.id', $change->public_id);
});

it('shows the requested national ID in full only on a pending request and audits that view', function (): void {
    $user = giveNationalId(staffUser(RoleKey::Assessor), 41);
    $change = pendingChangeOf($user, ['new_national_id' => fictionalNationalId(42), 'current_password' => defaultPassword()]);
    $reviewer = systemAdmin();

    switchTo($reviewer)->getJson(IDENTITY_CHANGES)
        ->assertOk()
        ->assertJsonPath('data.0.requested_national_id_masked', '••••••'.substr(fictionalNationalId(42), -4))
        ->assertJsonPath('data.0.current_national_id_masked', '••••••'.substr(fictionalNationalId(41), -4))
        ->assertJsonMissingPath('data.0.requested_national_id');
    switchTo($reviewer)->getJson(IDENTITY_CHANGES."/{$change->public_id}")
        ->assertOk()
        ->assertJsonPath('data.requested_national_id', fictionalNationalId(42));

    expect(Audit::query()->where('event', AuditEvent::IdentityChangeViewed->value)->count())->toBe(1);
    expect(Audit::query()->get()->toJson())->not->toContain(fictionalNationalId(42))->not->toContain(fictionalNationalId(41));
});

it('shows each user identity history to reviewers with masked national IDs', function (): void {
    $user = giveNationalId(staffUser(RoleKey::Assessor), 51);
    $change = pendingChangeOf($user, ['new_national_id' => fictionalNationalId(52), 'current_password' => defaultPassword()]);
    switchTo(systemAdmin())->postJson(IDENTITY_CHANGES."/{$change->public_id}/approve")->assertOk();

    $response = switchTo(systemAdmin())->getJson("/api/v1/users/{$user->id}/identity-history")
        ->assertOk()
        ->assertJsonPath('data.0.field', 'national_id')
        ->assertJsonPath('data.0.old_value', '••••••'.substr(fictionalNationalId(51), -4))
        ->assertJsonPath('data.0.new_value', '••••••'.substr(fictionalNationalId(52), -4))
        ->assertJsonPath('data.0.reason', CHANGE_REASON);
    expect($response->getContent())->not->toContain(fictionalNationalId(51));

    switchTo(staffUser(RoleKey::Assessor))->getJson("/api/v1/users/{$user->id}/identity-history")->assertForbidden();
});

it('keeps the identity history append-only', function (): void {
    $user = staffUser(RoleKey::Assessor);
    $change = pendingChangeOf($user);
    switchTo(systemAdmin())->postJson(IDENTITY_CHANGES."/{$change->public_id}/approve")->assertOk();
    $row = UserIdentityChange::query()->sole();

    expect(fn (): bool => $row->forceFill(['field' => 'national_id'])->save())->toThrow(AppendOnlyViolation::class)
        ->and(fn (): ?bool => $row->delete())->toThrow(AppendOnlyViolation::class);
});

it('routes an administrator name correction through a second reviewer', function (): void {
    $administrator = systemAdmin();
    $target = staffUser(RoleKey::Assessor);

    switchTo($administrator)->putJson("/api/v1/users/{$target->id}", ['name' => 'عبدالله الدوسري'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $response = switchTo($administrator)->postJson("/api/v1/users/{$target->id}/name-correction", ['new_name' => 'عبدالله الدوسري', 'reason' => CHANGE_REASON])
        ->assertCreated()
        ->assertJsonPath('data.on_behalf', true);
    $id = $response->json('data.id');

    switchTo($administrator)->postJson(IDENTITY_CHANGES."/{$id}/approve")->assertForbidden();
    switchTo(staffUser(RoleKey::SystemAdmin))->postJson(IDENTITY_CHANGES."/{$id}/approve")->assertOk();
    expect($target->refresh()->name)->toBe('عبدالله الدوسري');
});

it('still lets an administrator correct the name of an invitee who never signed in', function (): void {
    $invitee = User::factory()->withRole(RoleKey::Assessor)->create(['status' => 'invited']);

    switchTo(systemAdmin())->putJson("/api/v1/users/{$invitee->id}", ['name' => 'عبدالله الدوسري'])->assertOk();
    expect($invitee->refresh()->name)->toBe('عبدالله الدوسري');
});

it('refuses the name correction endpoint to users without user management and on oneself', function (): void {
    $administrator = systemAdmin();

    switchTo(staffUser(RoleKey::Assessor))->postJson("/api/v1/users/{$administrator->id}/name-correction", ['new_name' => 'اسم آخر', 'reason' => CHANGE_REASON])->assertForbidden();
    switchTo($administrator)->postJson("/api/v1/users/{$administrator->id}/name-correction", ['new_name' => 'اسم آخر', 'reason' => CHANGE_REASON])->assertForbidden();
});

it('lists the requester own requests with masked values only', function (): void {
    $user = giveNationalId(staffUser(RoleKey::Assessor), 61);
    pendingChangeOf($user, ['new_national_id' => fictionalNationalId(62), 'current_password' => defaultPassword()]);

    $response = switchTo($user)->getJson(MY_IDENTITY_CHANGES)->assertOk()->assertJsonPath('data.0.status', 'pending');

    expect($response->getContent())->not->toContain(fictionalNationalId(62));
});
