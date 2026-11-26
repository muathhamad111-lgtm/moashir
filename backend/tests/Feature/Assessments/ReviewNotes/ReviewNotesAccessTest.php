<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\AssessmentReviewNote;
use App\Models\Identity\Role;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Notification::fake();
});

it('never lets the entity read or write the internal notes', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1')])->assertOk();
    $owner = ownerOf($assessment->entity);

    saveReviewNotes($owner, $assessment, [mainReviewNote($version, 'M1')])->assertForbidden();
    $review = switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertForbidden();

    expect($review->json())->not->toHaveKey('data.review_notes')
        ->and(AssessmentReviewNote::query()->count())->toBe(1);
});

it('refuses staff outside the request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($version, assessorUser());
    $stranger = assessorUser();
    $otherPath = followerOfPaths(AssessmentPath::Advanced);

    saveReviewNotes($stranger, $assessment, [mainReviewNote($version, 'M1')])->assertNotFound();
    switchTo($stranger)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertNotFound();
    saveReviewNotes($otherPath, $assessment, [mainReviewNote($version, 'M1')])->assertNotFound();
    saveReviewNotes(staffUser(RoleKey::ContentOfficer), $assessment, [mainReviewNote($version, 'M1')])->assertNotFound();

    expect(AssessmentReviewNote::query()->count())->toBe(0);
});

it('refuses a filtering officer of the cycle who reads the advanced request', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = initiallyAssessedRequest($cycle, $version, $assessor);
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);

    switchTo($officer)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk();
    switchTo($officer)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertForbidden();
    saveReviewNotes($officer, $assessment, [mainReviewNote($version, 'M1')])->assertForbidden();

    expect(AssessmentReviewNote::query()->count())->toBe(0);
});

it('lets an assessor whose assignment ended read the notes but not write them', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessor = assessorUser();
    $assessment = startedInitialAssessment($cycle, $version, $assessor);
    saveReviewNotes($assessor, $assessment, [pointReviewNote($version, 'M1.2.2')])->assertOk()->assertJsonPath('data.review_notes.0.author.role', 'assessor');
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'awaiting_filtering');

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.can_write_review_notes', false)
        ->assertJsonCount(1, 'data.review_notes')
        ->assertJsonPath('data.review_notes.0.is_mine', true);
    saveReviewNotes($assessor, $assessment, [pointReviewNote($version, 'M1.2.2', 'نص بعد انتهاء الإسناد.')])->assertForbidden();

    $follower = requestFollower();
    saveReviewNotes($follower, $assessment, [mainReviewNote($version, 'M1')])->assertOk()->assertJsonPath('data.can_write_review_notes', true);
});

it('omits the notes for a reader of the review without the notes permission', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveReviewNotes($assessor, $assessment, [subReviewNote($version, 'M1.1')])->assertOk();
    Role::query()->where('name', 'request_follower')->firstOrFail()->revokePermissionTo([PermissionKey::RequestsNotesRead->value, PermissionKey::RequestsNotesWrite->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $follower = requestFollower();

    $review = switchTo($follower)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.can_write_review_notes', false);
    saveReviewNotes($follower, $assessment, [mainReviewNote($version, 'M1')])->assertForbidden();

    expect($review->json('data'))->not->toHaveKey('review_notes');
});

it('lets an assessor without the write permission read the notes but not write them', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    Role::query()->where('name', 'assessor')->firstOrFail()->revokePermissionTo(PermissionKey::RequestsNotesWrite->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    saveReviewNotes($assessor->refresh(), $assessment, [subReviewNote($version, 'M1.1')])->assertForbidden();
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.can_write_review_notes', false)
        ->assertJsonPath('data.review_notes', []);
});
