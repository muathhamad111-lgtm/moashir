<?php

declare(strict_types=1);

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Assessments\Assessment;
use Illuminate\Support\Facades\Date;

function ownerSeesStatus(Assessment $assessment, string $status): void
{
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.status', $status)
        ->assertJsonPath('data.is_open', true);
    switchTo($owner)->getJson('/api/v1/assessments?filter[path]=advanced')->assertOk()->assertJsonPath('data.0.status', $status);
    expect(boardWidget(boardOf($owner, 'entity'), 'paths')['award']['open_request']['status'])->toBe($status);
}

dataset('initial review states', [
    'awaiting review assignment' => [fn (): Assessment => submittedAdvancedRequest(openAwardCycle($version = publishedAccreditedCriteria()), $version)],
    'assigned' => [fn (): Assessment => assignedAdvancedRequest(openAwardCycle($version = publishedAccreditedCriteria()), $version, assessorUser())],
    'under initial assessment' => [fn (): Assessment => startedInitialAssessment(openAwardCycle($version = publishedAccreditedCriteria()), $version, assessorUser())],
]);

it('shows the owner "under review" for every initial review state', function (Closure $request): void {
    $assessment = $request();

    ownerSeesStatus($assessment, 'under_review');

    $history = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->json('data.status_history');
    expect(array_column($history, 'to'))->not->toContain($assessment->status->value);
})->with('initial review states');

it('keeps the real initial review state for staff', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $follower = requestFollower();
    $assessment = assignedAdvancedRequest(openAwardCycle($version), $version, $assessor, $follower);

    switchTo($follower)->getJson("/api/v1/requests/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'assigned');
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'assigned');
});

it('hides the initial review states from the owner status filter', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = assignedAdvancedRequest(openAwardCycle($version), $version, assessorUser());
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->getJson('/api/v1/assessments?filter[status]=assigned&filter[path]=advanced')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo($owner)->getJson('/api/v1/assessments?filter[path]=advanced&filter[is_open]=true')->assertOk()->assertJsonPath('meta.pagination.total', 1);
});

it('keeps a completion request visible to the owner, since the entity must act on it', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAdvancedRequest(openAwardCycle($version), $version);
    $assessment->forceFill(['status' => AdvancedAssessmentStatus::NeedsCompletion])->save();

    ownerSeesStatus($assessment, 'needs_completion');
});

it('shows the owner the real status once the results are announced', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = assignedAdvancedRequest($cycle, $version, assessorUser());

    $cycle->forceFill(['announced_at' => Date::now()])->save();

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->assertJsonPath('data.status', 'assigned');
});

it('masks the owner states without touching the staff-only filtering mask', function (): void {
    expect(AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::ownerHiddenStates()))
        ->toContain('awaiting_review_assignment', 'assigned', 'under_initial_assessment', 'awaiting_filtering', 'approved')
        ->not->toContain('draft', 'needs_completion', 'declined_incomplete', 'withdrawn', 'refunded')
        ->and(AdvancedAssessmentStatus::Assigned->isHiddenFromEntityUntilAnnouncement())->toBeFalse();
});

it('keeps both owner rules together: the submission reads under review and the completion step masks its origin', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAdvancedRequest(openAwardCycle($version), $version);
    $follower = requestFollower();

    switchTo($follower)->postJson("/api/v1/requests/{$assessment->id}/return-for-completion", [
        'notes' => RETURN_NOTES,
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv', 'note' => 'حدّثوا وصف السياسة المعتمدة.']],
        'deadline_at' => Date::now()->addDays(10)->toIso8601String(),
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk();

    $steps = static fn (array $history): array => array_map(static fn (array $step): array => [$step['verb'], $step['from'], $step['to']], $history);
    $owner = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()
        ->assertJsonPath('data.status', 'needs_completion')
        ->json('data.status_history');
    $staff = switchTo($follower)->getJson("/api/v1/assessments/{$assessment->id}")->assertOk()->json('data.status_history');

    expect($steps($owner))->toBe([
        ['start', null, 'draft'],
        ['submit', 'draft', 'under_review'],
        ['return_for_completion', 'under_review', 'needs_completion'],
    ])->and($steps($staff)[1])->toBe(['submit', 'draft', 'awaiting_review_assignment']);
});
