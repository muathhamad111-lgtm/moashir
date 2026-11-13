<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Identity\SignInCodeVerdict;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use App\Services\Identity\OtpIssueThrottle;
use App\Services\Identity\SignInCodeEligibility;
use Illuminate\Support\Facades\Date;

function signInCodeVerdictOf(?User $user): SignInCodeVerdict
{
    return app(SignInCodeEligibility::class)->of($user);
}

it('lets an active entity owner receive a sign-in code', function (): void {
    expect(signInCodeVerdictOf(ownerOf(entityWithOwner())))->toBe(SignInCodeVerdict::SignInCode)
        ->and(SignInCodeVerdict::SignInCode->sendsCode())->toBeTrue();
});

it('gives a pending entity its registration code', function (): void {
    expect(signInCodeVerdictOf(ownerOf(entityWithOwner(static fn ($factory) => $factory->pendingVerification()))))->toBe(SignInCodeVerdict::RegistrationCode)
        ->and(SignInCodeVerdict::RegistrationCode->sendsCode())->toBeTrue();
});

it('ignores every account that may not use a code, with a reason', function (Closure $account, SignInCodeVerdict $expected): void {
    $verdict = signInCodeVerdictOf($account());

    expect($verdict)->toBe($expected)
        ->and($verdict->sendsCode())->toBeFalse();
})->with([
    'unknown' => [fn (): ?User => null, SignInCodeVerdict::Unknown],
    'staff' => [systemAdmin(...), SignInCodeVerdict::NotEntity],
    'staff without authenticator' => [fn (): User => User::factory()->withRole(RoleKey::Assessor)->create(), SignInCodeVerdict::NotEntity],
    'Super Admin' => [superAdmin(...), SignInCodeVerdict::NotEntity],
    'locked' => [fn (): User => tap(ownerOf(entityWithOwner()), fn (User $owner) => $owner->forceFill(['locked_until' => Date::now()->addMinutes(15)])->save()), SignInCodeVerdict::Locked],
    'suspended' => [fn (): User => tap(ownerOf(entityWithOwner()), fn (User $owner) => $owner->forceFill(['status' => UserStatus::Suspended])->save()), SignInCodeVerdict::Inactive],
    'deactivated entity' => [fn (): User => ownerOf(entityWithOwner(static fn ($factory) => $factory->deactivated())), SignInCodeVerdict::Inactive],
    'per-account limit reached' => [fn (): User => tap(ownerOf(entityWithOwner()), fn (User $owner) => collect(range(1, 5))->each(fn () => app(OtpIssueThrottle::class)->hit($owner))), SignInCodeVerdict::Throttled],
]);

it('stops ignoring a locked account once the lockout has elapsed', function (): void {
    $owner = ownerOf(entityWithOwner());
    $owner->forceFill(['locked_until' => Date::now()->addMinutes(15)])->save();

    $this->travel(16)->minutes();

    expect(signInCodeVerdictOf($owner->refresh()))->toBe(SignInCodeVerdict::SignInCode);
});
