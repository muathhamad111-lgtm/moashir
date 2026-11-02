<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Identity\User;
use App\Services\Assessments\AssessorEligibility;

it('accepts an active assessor and refuses the actor himself', function (): void {
    $eligibility = app(AssessorEligibility::class);
    $follower = staffUser(RoleKey::RequestFollower, RoleKey::Assessor);
    $assessor = staffUser(RoleKey::Assessor);

    expect($eligibility->assertEligible($follower, $assessor->id)->id)->toBe($assessor->id)
        ->and(fn () => $eligibility->assertEligible($follower, $follower->id))->toThrow(AssessmentRejected::class, 'sod_self_assignment');
});

it('refuses accounts that cannot assess', function (Closure $candidate): void {
    expect(fn () => app(AssessorEligibility::class)->assertEligible(systemAdmin(), $candidate()->id))->toThrow(AssessmentRejected::class, 'assessor.invalid');
})->with([
    'requests follower' => [fn (): User => staffUser(RoleKey::RequestFollower)],
    'suspended assessor' => [function (): User {
        $user = staffUser(RoleKey::Assessor);
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        return $user;
    }],
    'super admin' => [superAdmin(...)],
    'entity account' => [entityUser(...)],
]);
