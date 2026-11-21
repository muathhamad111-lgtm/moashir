<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use App\Services\Identity\RequestPathScope;

it('lets an unrestricted follower and the Super Admin follow every reviewed path', function (Closure $user): void {
    $scope = app(RequestPathScope::class);
    $subject = $user();

    expect($scope->pathsFor($subject))->toBe([AssessmentPath::Accredited, AssessmentPath::Advanced])
        ->and($scope->isRestricted($subject))->toBeFalse()
        ->and($scope->excludedPathsFor($subject))->toBe([])
        ->and($scope->covers($subject, AssessmentPath::Advanced))->toBeTrue();
})->with([
    'follower' => [requestFollower(...)],
    'super admin' => [superAdmin(...)],
]);

it('limits a scoped follower to the granted paths and always covers the self assessment', function (): void {
    $scope = app(RequestPathScope::class);
    $follower = followerOfPaths(AssessmentPath::Advanced);

    expect($scope->pathsFor($follower))->toBe([AssessmentPath::Advanced])
        ->and($scope->isRestricted($follower))->toBeTrue()
        ->and($scope->excludedPathsFor($follower))->toBe([AssessmentPath::Accredited])
        ->and($scope->covers($follower, AssessmentPath::Accredited))->toBeFalse()
        ->and($scope->covers($follower, AssessmentPath::SelfAssessment))->toBeTrue();
});

it('finds the users following a path through the model scope', function (): void {
    $everyPath = requestFollower();
    $accredited = followerOfPaths(AssessmentPath::Accredited);
    $advanced = followerOfPaths(AssessmentPath::Advanced);

    $followers = User::query()->followingPath(AssessmentPath::Accredited)->pluck('id')->all();

    expect($followers)->toContain($everyPath->id, $accredited->id)->not->toContain($advanced->id);
});

it('maps assessor qualifications to their paths and back', function (): void {
    expect(AssessorQualification::forPath(AssessmentPath::Accredited))->toBe(AssessorQualification::Accredited)
        ->and(AssessorQualification::forPath(AssessmentPath::Advanced))->toBe(AssessorQualification::AdvancedInitial)
        ->and(AssessorQualification::forPath(AssessmentPath::SelfAssessment))->toBeNull()
        ->and(AssessorQualification::AdvancedInitial->path())->toBe(AssessmentPath::Advanced)
        ->and(AssessorQualification::Accredited->label()->toArray())->toBe(['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment']);
});

it('lists the qualifications of a user in the catalog order', function (): void {
    $assessor = qualifyAssessor(unqualifiedStaffUser(RoleKey::Assessor), AssessorQualification::AdvancedInitial, AssessorQualification::Accredited);

    expect($assessor->qualifications())->toBe([AssessorQualification::Accredited, AssessorQualification::AdvancedInitial])
        ->and($assessor->isQualifiedFor(AssessorQualification::Accredited))->toBeTrue()
        ->and(unqualifiedStaffUser(RoleKey::Assessor)->qualifications())->toBe([]);
});
