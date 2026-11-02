<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

dataset('actors', [
    'system administrator' => [systemAdmin(...)],
    'another super admin' => [superAdmin(...)],
]);

it('never lists Super Admin accounts', function (Closure $actor): void {
    $hidden = superAdmin();
    signIn($actor());

    $ids = collect($this->getJson('/api/v1/users?per_page=100')->assertOk()->json('data'))->pluck('id');

    expect($ids)->not->toContain($hidden->id);
})->with('actors');

it('answers 404 for every user endpoint that targets a Super Admin', function (Closure $actor): void {
    $hidden = superAdmin();
    signIn($actor());
    $reason = ['reason' => 'محاولة إدارية موثقة لأغراض الاختبار'];

    $this->getJson("/api/v1/users/{$hidden->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->putJson("/api/v1/users/{$hidden->id}", ['name' => 'اسم بديل'])->assertNotFound();
    $this->postJson("/api/v1/users/{$hidden->id}/suspend", $reason)->assertNotFound();
    $this->postJson("/api/v1/users/{$hidden->id}/activate")->assertNotFound();
    $this->postJson("/api/v1/users/{$hidden->id}/unlock")->assertNotFound();
    $this->postJson("/api/v1/users/{$hidden->id}/mfa/reset", $reason)->assertNotFound();
    $this->postJson("/api/v1/users/{$hidden->id}/invitation/resend")->assertNotFound();

    expect($hidden->refresh()->hasConfirmedTotp())->toBeTrue()
        ->and($hidden->isSuperAdmin())->toBeTrue();
})->with('actors');

it('rejects every attempt to assign the Super Admin through the API with a security event and alert', function (): void {
    Notification::fake();
    $watcher = superAdmin();
    signIn(systemAdmin());
    $target = staffUser(RoleKey::Assessor);

    $this->postJson('/api/v1/users', ['name' => 'سلمان المطيري', 'email' => 'new.user@moashir.example.sa', 'roles' => ['super_admin']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_not_assignable');
    $this->putJson("/api/v1/users/{$target->id}", ['roles' => ['assessor', 'super_admin']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'role_not_assignable');

    expect(Audit::query()->where('event', AuditEvent::RoleAssignmentRejected->value)->count())->toBe(2)
        ->and($target->refresh()->isSuperAdmin())->toBeFalse()
        ->and(User::query()->where('email', 'new.user@moashir.example.sa')->exists())->toBeFalse();
    Notification::assertSentTo($watcher, SecurityAlertNotification::class, static fn (SecurityAlertNotification $alert): bool => $alert->code()->value === 'SEC-05');
});

it('keeps super_admin out of the roles list, the matrix and the catalog', function (): void {
    signIn(superAdmin());

    expect(collect($this->getJson('/api/v1/roles')->json('data'))->pluck('key'))->not->toContain('super_admin')
        ->and($this->getJson('/api/v1/permissions')->json('data.matrix'))->not->toHaveKey('super_admin');
});

it('refuses any database change of the Super Admin flag outside the console command', function (): void {
    $user = staffUser(RoleKey::Assessor);

    expect(fn () => DB::transaction(fn () => DB::table('users')->where('id', $user->id)->update(['is_super_admin' => true])))->toThrow(QueryException::class, 'super_admin_flag')
        ->and(fn () => DB::transaction(fn () => $user->forceFill(['is_super_admin' => true])->save()))->toThrow(QueryException::class, 'super_admin_flag')
        ->and(fn () => DB::transaction(fn () => DB::table('users')->insert(['name' => 'حساب مزيف', 'email' => 'forged@moashir.example.sa', 'is_super_admin' => true, 'created_at' => now(), 'updated_at' => now()])))->toThrow(QueryException::class, 'super_admin_flag')
        ->and($user->refresh()->isSuperAdmin())->toBeFalse();
});

it('bypasses permission checks for the Super Admin through Gate::before only', function (): void {
    $superAdmin = superAdmin();

    expect($superAdmin->can('audit.export'))->toBeTrue()
        ->and($superAdmin->can('an.unknown.ability'))->toBeTrue()
        ->and(systemAdmin()->can('an.unknown.ability'))->toBeFalse();
});

it('denies unknown permission keys to every regular role', function (): void {
    expect(systemAdmin()->can('support.tickets.manage'))->toBeFalse();
});
