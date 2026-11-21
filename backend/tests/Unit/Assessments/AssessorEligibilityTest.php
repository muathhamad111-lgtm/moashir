<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Identity\User;
use App\Services\Assessments\AssessorEligibility;

it('accepts an active assessor and refuses the actor himself', function (): void {
    $eligibility = app(AssessorEligibility::class);
    $follower = staffUser(RoleKey::RequestFollower, RoleKey::Assessor);
    $assessor = staffUser(RoleKey::Assessor);

    expect($eligibility->assertEligible($follower, $assessor->id, AssessmentPath::Accredited)->id)->toBe($assessor->id)
        ->and(fn () => $eligibility->assertEligible($follower, $follower->id, AssessmentPath::Accredited))->toThrow(AssessmentRejected::class, 'sod_self_assignment');
});

it('refuses accounts that cannot assess', function (Closure $candidate): void {
    expect(fn () => app(AssessorEligibility::class)->assertEligible(systemAdmin(), $candidate()->id, AssessmentPath::Accredited))->toThrow(AssessmentRejected::class, 'assessor.invalid');
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

it('accepts an assessor only for the paths they are qualified for', function (): void {
    $eligibility = app(AssessorEligibility::class);
    $actor = systemAdmin();
    $accreditedOnly = qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::Accredited);
    $advancedOnly = qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::AdvancedInitial);

    expect($eligibility->assertEligible($actor, $accreditedOnly->id, AssessmentPath::Accredited)->id)->toBe($accreditedOnly->id)
        ->and(fn () => $eligibility->assertEligible($actor, $advancedOnly->id, AssessmentPath::Accredited))->toThrow(AssessmentRejected::class, 'assessor.not_qualified')
        ->and(fn () => $eligibility->assertEligible($actor, $accreditedOnly->id, AssessmentPath::Advanced))->toThrow(AssessmentRejected::class, 'assessor.not_qualified')
        ->and($eligibility->find($actor, $advancedOnly->id, AssessmentPath::Accredited))->toBeNull()
        ->and($eligibility->find($actor, $accreditedOnly->id, AssessmentPath::Accredited)?->id)->toBe($accreditedOnly->id)
        ->and($eligibility->eligibleFor($actor, AssessmentPath::Accredited)->pluck('id')->all())->toBe([$accreditedOnly->id])
        ->and($eligibility->eligibleFor($actor, AssessmentPath::Advanced)->pluck('id')->all())->toBe([$advancedOnly->id]);
});

it('refuses an assessor without any qualified path', function (): void {
    $unqualified = unqualifiedStaffUser(RoleKey::Assessor);

    expect(fn () => app(AssessorEligibility::class)->assertEligible(systemAdmin(), $unqualified->id, AssessmentPath::Accredited))
        ->toThrow(AssessmentRejected::class, 'assessor.not_qualified');
});
