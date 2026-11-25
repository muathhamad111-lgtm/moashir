<?php

declare(strict_types=1);

use App\Enums\Assessments\CompletionRequestStatus;
use App\Enums\Assessments\ScoreLevel;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Assessments\AssessmentScore;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-01 08:00:00');
});

/**
 * @return list<array<string, mixed>>
 */
function rescoreRequestItems(CriteriaVersion $version): array
{
    return [
        ['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv', 'note' => 'لم تُرفق الخطة المعتمدة'],
        ['sub_criterion_id' => subOf($version, 'M1.2')->id, 'kind' => 'evidence', 'note' => 'أرفقوا الخطة التشغيلية'],
        ['main_criterion_id' => mainOf($version, 'M1')->id, 'kind' => 'evidence', 'note' => 'أرفقوا محضر مجلس الإدارة'],
    ];
}

/**
 * @return array{0: Assessment, 1: AssessmentCompletionRequest}
 */
function rescoreRoundTwo(CriteriaVersion $version, User $assessor): array
{
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ACCREDITED_FULL_MARKS);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        ...roundCompletionPayload($version),
        'items' => rescoreRequestItems($version),
    ])->assertOk();

    $owner = ownerOf($assessment->entity);
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'وصف محدث للسياسة المعتمدة.']])->assertOk();
    uploadEvidence($owner, $assessment, $version, 'M1.2', null)->assertCreated();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');

    return [$assessment->refresh(), AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->sole()];
}

function completeRescoredReview(User $actor, Assessment $assessment): TestResponse
{
    return switchTo($actor)->postJson("/api/v1/assessments/{$assessment->id}/complete");
}

/**
 * @return array{level: string, id: int, code: string}
 */
function rescoreUnit(CriteriaVersion $version, string $code): array
{
    return str_starts_with($code, 'M1.2.')
        ? ['level' => ScoreLevel::Point->value, 'id' => pointOf($version, $code)->id, 'code' => $code]
        : ['level' => ScoreLevel::Sub->value, 'id' => subOf($version, $code)->id, 'code' => $code];
}

function rescoreField(CriteriaVersion $version, string $code): string
{
    $unit = rescoreUnit($version, $code);

    return 'scores.'.($unit['level'] === ScoreLevel::Sub->value ? 'subs' : 'points').'.'.$unit['id'];
}

function currentRescoreRow(Assessment $assessment, CriteriaVersion $version, string $code): AssessmentScore
{
    $unit = rescoreUnit($version, $code);
    $column = $unit['level'] === ScoreLevel::Sub->value ? 'sub_criterion_id' : 'guiding_point_id';

    return AssessmentScore::query()->where('assessment_id', $assessment->id)->where($column, $unit['id'])->current()->sole();
}

it('refuses to complete while the units of the resubmitted items have no result of the current round, even with every unit scored', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment] = rescoreRoundTwo($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M2.1' => '6']);

    $response = completeRescoredReview($assessor, $assessment)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'scores.round_incomplete')
        ->assertJsonPath('message', __('errors.scores.round_incomplete'))
        ->assertJsonPath('meta.missing', [rescoreUnit($version, 'M1.1'), rescoreUnit($version, 'M1.2.1'), rescoreUnit($version, 'M1.2.2')])
        ->assertJsonValidationErrors([rescoreField($version, 'M1.1'), rescoreField($version, 'M1.2.1'), rescoreField($version, 'M1.2.2')])
        ->assertJsonMissingValidationErrors([rescoreField($version, 'M2.1')]);

    expect($response->json('errors'))->toBe([
        rescoreField($version, 'M1.1') => [__('errors.scores.round_item_missing')],
        rescoreField($version, 'M1.2.1') => [__('errors.scores.round_item_missing')],
        rescoreField($version, 'M1.2.2') => [__('errors.scores.round_item_missing')],
    ])
        ->and($assessment->refresh()->status->value)->toBe('under_review')
        ->and($assessment->currentResult)->toBeNull();
});

it('completes once every unit of the resubmitted items is scored in the current round', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment, $round] = rescoreRoundTwo($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '9']);

    completeRescoredReview($assessor, $assessment)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'scores.round_incomplete')
        ->assertJsonPath('meta.missing', [rescoreUnit($version, 'M1.2.1'), rescoreUnit($version, 'M1.2.2')]);

    saveRoundScores($assessor, $assessment, $version, ['M1.2.1' => '7', 'M1.2.2' => '5']);

    completeRescoredReview($assessor, $assessment)->assertOk()->assertJsonPath('data.status', 'completed');

    expect(currentRescoreRow($assessment, $version, 'M1.2.2')->completion_request_id)->toBe($round->id)
        ->and(currentRescoreRow($assessment, $version, 'M2.1')->completion_request_id)->toBeNull();
});

it('completes after the assessor keeps the same results in the current round, as the review screen confirms them', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment, $round] = rescoreRoundTwo($version, $assessor);
    $review = switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk();

    foreach (['M1.1', 'M1.2.1', 'M1.2.2'] as $code) {
        $unit = rescoreUnit($version, $code);
        $group = $unit['level'] === ScoreLevel::Sub->value ? 'subs' : 'points';
        $saved = $review->json("data.scores.{$group}.{$unit['id']}.raw_value");

        switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", [
            'scores' => ['subs' => [], 'points' => [], $group => [(string) $unit['id'] => $saved]],
        ])->assertOk();
    }

    completeRescoredReview($assessor, $assessment)->assertOk()->assertJsonPath('data.status', 'completed');

    expect(currentRescoreRow($assessment, $version, 'M1.1')->raw_value)->toBe('8.00')
        ->and(currentRescoreRow($assessment, $version, 'M1.1')->completion_request_id)->toBe($round->id)
        ->and(currentRescoreRow($assessment, $version, 'M1.2.1')->raw_value)->toBe('9.00')
        ->and(currentRescoreRow($assessment, $version, 'M1.2.1')->completion_request_id)->toBe($round->id);
});

it('tags a unit first scored in the current round with that round and lists it there, without counting it for the resubmitted items', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '8', 'M1.2.1' => '9', 'M1.2.2' => '6']);
    $round = AssessmentCompletionRequest::query()->forceCreate([
        'assessment_id' => $assessment->id,
        'notes' => 'طلب استكمال سابق لاشتراط تقييم جميع البنود.',
        'items' => [['sub_code' => 'M1.1', 'kind' => 'cv', 'note' => null], ['sub_code' => 'M1.2', 'kind' => 'evidence', 'note' => null]],
        'deadline_at' => Date::now()->addDays(3),
        'expiry_outcome' => 'decline',
        'status' => CompletionRequestStatus::Resubmitted,
        'requested_by_user_id' => $assessor->id,
        'resolved_at' => Date::now(),
    ]);

    saveRoundScores($assessor, $assessment, $version, ['M2.1' => '7']);

    $results = subOf($version, 'M2.1');

    expect(currentRescoreRow($assessment, $version, 'M2.1')->completion_request_id)->toBe($round->id);
    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review/rounds")->assertOk()
        ->assertJsonPath('data.requests.0.round', 2)
        ->assertJsonPath("data.units.sub-{$results->id}", [
            ['round' => 2, 'completion_request_id' => $round->id, 'raw_value' => '7.00', 'percent' => '70.00', 'scored_at' => '2026-11-01T08:00:00+00:00'],
        ]);
    completeRescoredReview($assessor, $assessment)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'scores.round_incomplete')
        ->assertJsonPath('meta.missing', [rescoreUnit($version, 'M1.1'), rescoreUnit($version, 'M1.2.1'), rescoreUnit($version, 'M1.2.2')]);
});

it('completes without a new result when the round was opened by a request that expired and continued as submitted', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ACCREDITED_FULL_MARKS);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        ...roundCompletionPayload($version),
        'items' => rescoreRequestItems($version),
        'expiry_outcome' => 'continue_as_submitted',
    ])->assertOk();

    Date::setTestNow('2026-11-09 08:00:00');
    Artisan::call('moashir:assessments:expire-completion-requests');

    expect($assessment->refresh()->status->value)->toBe('under_review')
        ->and(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->sole()->status)->toBe(CompletionRequestStatus::ExpiredContinued);

    completeRescoredReview($assessor, $assessment)->assertOk()->assertJsonPath('data.status', 'completed');
});

it('refuses the completion to anyone but the assignee before checking the current round', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment] = rescoreRoundTwo($version, $assessor);

    foreach ([requestFollower(), systemAdmin(), ownerOf($assessment->entity)] as $reader) {
        completeRescoredReview($reader, $assessment)->assertForbidden();
    }

    completeRescoredReview(assessorUser(), $assessment)->assertNotFound();

    expect($assessment->refresh()->status->value)->toBe('under_review');
});
