<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Notifications\Identity\PasswordResetLinkNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('grants the Super Admin to an existing active user after the typed confirmation', function (): void {
    $user = User::factory()->withConfirmedTotp()->create();
    DB::table('sessions')->insert(['id' => 'before-grant', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->artisan('moashir:super-admin', ['action' => 'grant', '--email' => $user->email])
        ->expectsQuestion('Type the full email of the account ('.new EmailAddress($user->email)->masked().') to confirm', $user->email)
        ->assertSuccessful();

    $user->refresh();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->hasConfirmedTotp())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::SuperAdminGranted->value)->where('auditable_id', $user->id)->exists())->toBeTrue();
    Notification::assertSentTo($user, SecurityAlertNotification::class);
});

it('changes nothing when the confirmation does not match', function (): void {
    $user = User::factory()->create();

    $this->artisan('moashir:super-admin', ['action' => 'grant', '--email' => $user->email])
        ->expectsQuestion('Type the full email of the account ('.new EmailAddress($user->email)->masked().') to confirm', 'someone.else@moashir.example.sa')
        ->assertFailed();

    expect($user->refresh()->isSuperAdmin())->toBeFalse();
});

it('refuses a user who holds other roles (SoD-8)', function (): void {
    $user = systemAdmin();

    $this->artisan('moashir:super-admin', ['action' => 'grant', '--email' => $user->email])
        ->expectsQuestion('Type the full email of the account ('.new EmailAddress($user->email)->masked().') to confirm', $user->email)
        ->expectsOutputToContain('holds other roles')
        ->assertFailed();

    expect($user->refresh()->isSuperAdmin())->toBeFalse();
});

it('refuses an entity account and an unknown e-mail', function (): void {
    $entity = entityUser();

    $this->artisan('moashir:super-admin', ['action' => 'grant', '--email' => $entity->email])
        ->expectsQuestion('Type the full email of the account ('.new EmailAddress($entity->email)->masked().') to confirm', $entity->email)
        ->assertFailed();

    $this->artisan('moashir:super-admin', ['action' => 'grant', '--email' => 'nobody@moashir.example.sa'])
        ->expectsQuestion('Type the full email of the account (n***@moashir.example.sa) to confirm', 'nobody@moashir.example.sa')
        ->expectsOutputToContain('No user exists')
        ->assertFailed();
});

it('refuses to exceed the maximum number of Super Admin accounts', function (): void {
    superAdmin();
    superAdmin();
    $third = User::factory()->create();

    $this->artisan('moashir:super-admin', ['action' => 'grant', '--email' => $third->email])
        ->expectsQuestion('Type the full email of the account ('.new EmailAddress($third->email)->masked().') to confirm', $third->email)
        ->expectsOutputToContain('maximum number')
        ->assertFailed();
});

it('creates the initial account from SUPER_ADMIN_INITIAL_EMAIL and sends a password link', function (): void {
    config()->set('moashir.super_admin.initial_email', 'technical.owner@moashir.example.sa');

    $this->artisan('moashir:super-admin', ['action' => 'grant', '--name' => 'المالك التقني'])
        ->expectsQuestion('Type the full email of the account (t***@moashir.example.sa) to confirm', 'technical.owner@moashir.example.sa')
        ->assertSuccessful();

    $owner = User::query()->where('email', 'technical.owner@moashir.example.sa')->firstOrFail();
    expect($owner->isSuperAdmin())->toBeTrue()->and($owner->roles)->toBeEmpty();
    Notification::assertSentTo($owner, PasswordResetLinkNotification::class);
});

it('refuses to revoke the last Super Admin unless explicitly allowed', function (): void {
    $only = superAdmin();
    $question = 'Type the full email of the account ('.new EmailAddress($only->email)->masked().') to confirm';

    $this->artisan('moashir:super-admin', ['action' => 'revoke', '--email' => $only->email])
        ->expectsQuestion($question, $only->email)
        ->expectsOutputToContain('last Super Admin')
        ->assertFailed();

    $this->artisan('moashir:super-admin', ['action' => 'revoke', '--email' => $only->email, '--allow-last' => true])
        ->expectsQuestion($question, $only->email)
        ->assertSuccessful();

    expect($only->refresh()->isSuperAdmin())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::SuperAdminRevoked->value)->exists())->toBeTrue();
});

it('lists Super Admin accounts with masked e-mails only', function (): void {
    $superAdmin = superAdmin();
    [$local] = explode('@', $superAdmin->email);

    $this->artisan('moashir:super-admin', ['action' => 'list'])
        ->expectsOutputToContain(new EmailAddress($superAdmin->email)->masked())
        ->doesntExpectOutputToContain($local.'@')
        ->assertSuccessful();
});

it('rejects an unknown action', function (): void {
    $this->artisan('moashir:super-admin', ['action' => 'promote'])->assertExitCode(2);
});

it('refuses a grant without an e-mail when no initial e-mail is configured', function (): void {
    $this->artisan('moashir:super-admin', ['action' => 'grant'])->expectsOutputToContain('SUPER_ADMIN_INITIAL_EMAIL')->assertFailed();
});

it('never exposes a grant or revoke route over HTTP', function (): void {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map(static fn ($route): string => $route->uri());

    expect($uris->filter(static fn (string $uri): bool => str_contains($uri, 'super-admin') || str_contains($uri, 'super_admin')))->toBeEmpty();
});

it('uses a staff role with assigned roles as unaffected by the console command', function (): void {
    $staff = staffUser(RoleKey::Assessor);

    expect($staff->isSuperAdmin())->toBeFalse();
});
