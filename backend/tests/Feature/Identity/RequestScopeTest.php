<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\UserRequestPath;
use Illuminate\Support\Facades\Notification;

const REQUEST_SCOPE = '/api/v1/users/%d/request-scope';

beforeEach(function (): void {
    Notification::fake();
});

it('limits a follower to chosen request paths and restores every path, auditing both changes', function (): void {
    $follower = requestFollower();

    switchTo(systemAdmin())->putJson(sprintf(REQUEST_SCOPE, $follower->id), ['all_paths' => false, 'paths' => ['accredited']])
        ->assertOk()
        ->assertJsonPath('data.request_scope', ['all_paths' => false, 'paths' => ['accredited']]);

    expect(UserRequestPath::query()->where('user_id', $follower->id)->pluck('path')->all())->toBe([AssessmentPath::Accredited]);

    test()->putJson(sprintf(REQUEST_SCOPE, $follower->id), ['all_paths' => true])
        ->assertOk()
        ->assertJsonPath('data.request_scope', ['all_paths' => true, 'paths' => ['accredited', 'advanced']]);

    $audits = Audit::query()->where('event', AuditEvent::UserRequestPathsChanged->value)->orderBy('id')->get();

    expect(UserRequestPath::query()->where('user_id', $follower->id)->count())->toBe(0)
        ->and($audits)->toHaveCount(2)
        ->and($audits[0]->old_values)->toBe(['request_paths' => ['all']])
        ->and($audits[0]->new_values)->toBe(['request_paths' => ['accredited']])
        ->and($audits[1]->new_values)->toBe(['request_paths' => ['all']]);
});

it('keeps existing followers on every path by default', function (): void {
    $follower = requestFollower();

    switchTo(systemAdmin())->getJson("/api/v1/users/{$follower->id}")->assertOk()
        ->assertJsonPath('data.request_scope', ['all_paths' => true, 'paths' => ['accredited', 'advanced']]);
});

it('requires a reason from the Super Admin', function (): void {
    $follower = requestFollower();

    switchTo(superAdmin())->putJson(sprintf(REQUEST_SCOPE, $follower->id), ['all_paths' => false, 'paths' => ['advanced']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'reason_required');
    test()->putJson(sprintf(REQUEST_SCOPE, $follower->id), ['all_paths' => false, 'paths' => ['advanced'], 'reason' => 'قصر المتابع على مسار الجائزة'])
        ->assertOk()
        ->assertJsonPath('data.request_scope.paths', ['advanced']);
});

it('refuses users without the users management permission, entities and guests', function (): void {
    $follower = requestFollower();
    $payload = ['all_paths' => false, 'paths' => ['accredited']];

    test()->putJson(sprintf(REQUEST_SCOPE, $follower->id), $payload)->assertUnauthorized();
    switchTo(requestFollower())->putJson(sprintf(REQUEST_SCOPE, $follower->id), $payload)->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->putJson(sprintf(REQUEST_SCOPE, $follower->id), $payload)->assertForbidden();
    switchTo(systemAdmin())->putJson(sprintf(REQUEST_SCOPE, superAdmin()->id), $payload)->assertNotFound();

    expect(UserRequestPath::query()->count())->toBe(0);
});

it('refuses invalid request scopes', function (array $payload, string $field): void {
    switchTo(systemAdmin())->putJson(sprintf(REQUEST_SCOPE, requestFollower()->id), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing flag' => [['paths' => ['accredited']], 'all_paths'],
    'no path' => [['all_paths' => false, 'paths' => []], 'paths'],
    'missing paths' => [['all_paths' => false], 'paths'],
    'self path' => [['all_paths' => false, 'paths' => ['self']], 'paths.0'],
    'unknown path' => [['all_paths' => false, 'paths' => ['bag']], 'paths.0'],
    'duplicate' => [['all_paths' => false, 'paths' => ['accredited', 'accredited']], 'paths.0'],
]);

it('limits only accounts that follow the queue and never the actor himself', function (): void {
    $administrator = staffUser(RoleKey::SystemAdmin, RoleKey::RequestFollower);

    switchTo($administrator)->putJson(sprintf(REQUEST_SCOPE, assessorUser()->id), ['all_paths' => false, 'paths' => ['accredited']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'request_scope.not_follower')
        ->assertJsonValidationErrors(['paths']);
    test()->putJson(sprintf(REQUEST_SCOPE, $administrator->id), ['all_paths' => false, 'paths' => ['accredited']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'sod_self_grant');
});
