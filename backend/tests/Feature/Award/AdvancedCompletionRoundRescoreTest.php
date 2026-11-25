<?php

declare(strict_types=1);

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

function advancedRescoreRoundTwo(CriteriaVersion $version, User $assessor): Assessment
{
    $assessment = startedInitialAssessment(openAwardCycle($version), $version, $assessor);
    scoreEveryUnit($assessor, $assessment);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => RETURN_NOTES,
        'items' => [
            ['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv', 'note' => 'أرفقوا محضر اعتماد السياسة.'],
            ['main_criterion_id' => mainOf($version, 'M1')->id, 'kind' => 'evidence', 'note' => 'أرفقوا الخطة المعتمدة.'],
        ],
        'deadline_at' => Date::now()->addDays(7)->toIso8601String(),
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk()->assertJsonPath('data.status', 'needs_completion');

    $owner = ownerOf($assessment->entity);
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'أُرفق محضر اعتماد السياسة من مجلس الإدارة.']])->assertOk();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk();

    expect($assessment->refresh()->status->value)->toBe('under_initial_assessment');

    return $assessment;
}

it('refuses to complete a resumed initial assessment until the resubmitted items are scored in the current round', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = advancedRescoreRoundTwo($version, $assessor);
    $policy = subOf($version, 'M1.1');
    $complete = "/api/v1/assessments/{$assessment->id}/complete";

    switchTo($assessor)->postJson($complete)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'scores.round_incomplete')
        ->assertJsonPath('meta.missing', [['level' => 'sub', 'id' => $policy->id, 'code' => 'M1.1']])
        ->assertJsonValidationErrors(['scores.subs.'.$policy->id]);

    expect($assessment->refresh()->status->value)->toBe('under_initial_assessment');

    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '8']);

    switchTo($assessor)->postJson($complete)->assertOk()->assertJsonPath('data.status', 'awaiting_filtering');

    expect(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->sole()->status->value)->toBe('resubmitted');
});

it('refuses the completion of a resumed initial assessment to anyone but the assignee', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = advancedRescoreRoundTwo($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '8']);
    $complete = "/api/v1/assessments/{$assessment->id}/complete";

    switchTo(requestFollower())->postJson($complete)->assertForbidden();
    switchTo(ownerOf($assessment->entity))->postJson($complete)->assertForbidden();
    switchTo(assessorUser())->postJson($complete)->assertNotFound();

    expect($assessment->refresh()->status->value)->toBe('under_initial_assessment');
});
