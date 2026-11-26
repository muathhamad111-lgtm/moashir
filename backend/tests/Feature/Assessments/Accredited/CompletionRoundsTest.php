<?php

declare(strict_types=1);

use App\Enums\Assessments\CompletionRequestStatus;
use App\Enums\Assessments\ScoreLevel;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Assessments\AssessmentFeedbackItem;
use App\Models\Assessments\AssessmentScore;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function currentScoreOf(Assessment $assessment, CriteriaVersion $version, string $code): AssessmentScore
{
    return AssessmentScore::query()->where('assessment_id', $assessment->id)->where('sub_criterion_id', subOf($version, $code)->id)->current()->sole();
}

function roundsOf(User $viewer, Assessment $assessment): TestResponse
{
    return switchTo($viewer)->getJson("/api/v1/assessments/{$assessment->id}/review/rounds");
}

it('refuses a completion request until every unit is scored', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '8']);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", roundCompletionPayload($version))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'scores.incomplete')
        ->assertJsonPath('meta.missing.0.code', 'M1.2.1')
        ->assertJsonValidationErrors(['scores.points.'.pointOf($version, 'M1.2.1')->id, 'scores.subs.'.subOf($version, 'M2.1')->id])
        ->assertJsonMissingValidationErrors(['scores.subs.'.subOf($version, 'M1.1')->id]);

    expect($assessment->refresh()->status->value)->toBe('under_review')
        ->and(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->exists())->toBeFalse();

    saveRoundScores($assessor, $assessment, $version, ACCREDITED_FULL_MARKS);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", roundCompletionPayload($version))
        ->assertOk()
        ->assertJsonPath('data.status', 'needs_completion');
});

it('refuses a completion request from anyone but the assigned assessor even when every unit is scored', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    scoreEveryUnit($assessor, $assessment);
    $uri = "/api/v1/assessments/{$assessment->id}/request-completion";

    foreach ([requestFollower(), systemAdmin(), ownerOf($assessment->entity)] as $reader) {
        switchTo($reader)->postJson($uri, roundCompletionPayload($version))->assertForbidden();
    }

    switchTo(assessorUser())->postJson($uri, roundCompletionPayload($version))->assertNotFound();

    expect($assessment->refresh()->status->value)->toBe('under_review')
        ->and(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->exists())->toBeFalse();
});

it('leaves the scores and feedback of the first round without a completion request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview($version, $assessor);

    expect(AssessmentScore::query()->where('assessment_id', $assessment->id)->whereNotNull('completion_request_id')->exists())->toBeFalse()
        ->and(AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->count())->toBeGreaterThan(0)
        ->and(AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->whereNotNull('completion_request_id')->exists())->toBeFalse();
});

it('tags the scores and feedback written after the entity resubmitted with that completion request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment, $completion] = resubmittedForRoundTwo($version, $assessor);
    $policyId = (string) subOf($version, 'M1.1')->id;

    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '9']);
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => [$policyId => ['notes' => 'أرفقت الجهة الخطة المعتمدة بعد الاستكمال.']]])->assertOk();

    $note = AssessmentFeedbackItem::query()->where('assessment_id', $assessment->id)->where('sub_code', 'M1.1')->current()->sole();

    expect(currentScoreOf($assessment, $version, 'M1.1')->completion_request_id)->toBe($completion->id)
        ->and(currentScoreOf($assessment, $version, 'M2.1')->completion_request_id)->toBeNull()
        ->and($note->completion_request_id)->toBe($completion->id);
});

it('ignores a cancelled completion request when tagging a new score', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ACCREDITED_FULL_MARKS);
    AssessmentCompletionRequest::query()->forceCreate([
        'assessment_id' => $assessment->id,
        'notes' => 'طلب ملغى.',
        'items' => [['sub_code' => 'M1.1', 'kind' => 'cv', 'note' => null]],
        'deadline_at' => Date::now()->addDays(3),
        'expiry_outcome' => 'decline',
        'status' => CompletionRequestStatus::Cancelled,
        'requested_by_user_id' => $assessor->id,
        'resolved_at' => Date::now(),
    ]);

    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '9']);

    expect(currentScoreOf($assessment, $version, 'M1.1')->completion_request_id)->toBeNull();
});

it('records an unchanged score again when the assessor confirms it in a new round', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment, $completion] = resubmittedForRoundTwo($version, $assessor);
    $rows = AssessmentScore::query()->where('assessment_id', $assessment->id)->count();

    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '8']);

    $confirmed = currentScoreOf($assessment, $version, 'M1.1');

    expect($confirmed->completion_request_id)->toBe($completion->id)
        ->and($confirmed->raw_value)->toBe('8.00')
        ->and($confirmed->version)->toBe(2)
        ->and(currentScoreOf($assessment, $version, 'M2.1')->completion_request_id)->toBeNull()
        ->and(AssessmentScore::query()->where('assessment_id', $assessment->id)->count())->toBe($rows + 1);

    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '8']);

    expect(AssessmentScore::query()->where('assessment_id', $assessment->id)->count())->toBe($rows + 1);
});

it('creates no version for an unchanged score before any completion', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ACCREDITED_FULL_MARKS);
    $rows = AssessmentScore::query()->where('assessment_id', $assessment->id)->count();

    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '8']);

    expect(AssessmentScore::query()->where('assessment_id', $assessment->id)->count())->toBe($rows)
        ->and(currentScoreOf($assessment, $version, 'M1.1')->version)->toBe(1);
});

it('lists the completion rounds with the last score of each unit in each round', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment, $completion] = resubmittedForRoundTwo($version, $assessor);
    $policy = subOf($version, 'M1.1');
    $indicator = pointOf($version, 'M1.2.1');
    Date::setTestNow('2026-11-03 09:30:00');
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '9']);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '9.5']);

    foreach ([$assessor, requestFollower(), systemAdmin()] as $viewer) {
        roundsOf($viewer, $assessment)->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.id', $completion->id)
            ->assertJsonPath('data.requests.0.round', 2)
            ->assertJsonPath('data.requests.0.status', 'resubmitted')
            ->assertJsonPath('data.requests.0.requested_at', '2026-11-01T08:00:00+00:00')
            ->assertJsonPath('data.requests.0.resolved_at', '2026-11-01T08:00:00+00:00')
            ->assertJsonPath('data.requests.0.items', [[
                'main_criterion_id' => null,
                'sub_criterion_id' => $policy->id,
                'code' => 'M1.1',
                'name' => $policy->name->toArray(),
                'kind' => 'cv',
                'note' => 'لم تُرفق الخطة المعتمدة',
            ]])
            ->assertJsonPath("data.units.sub-{$policy->id}", [
                ['round' => 1, 'completion_request_id' => null, 'raw_value' => '8.00', 'percent' => '80.00', 'scored_at' => '2026-11-01T08:00:00+00:00'],
                ['round' => 2, 'completion_request_id' => $completion->id, 'raw_value' => '9.50', 'percent' => '95.00', 'scored_at' => '2026-11-03T09:30:00+00:00'],
            ])
            ->assertJsonPath("data.units.point-{$indicator->id}", [
                ['round' => 1, 'completion_request_id' => null, 'raw_value' => '9.00', 'percent' => '90.00', 'scored_at' => '2026-11-01T08:00:00+00:00'],
            ]);
    }
});

it('returns the first round only for a review without completion requests', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '6']);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '7']);

    $response = roundsOf($assessor, $assessment)->assertOk()
        ->assertJsonPath('data.requests', [])
        ->assertJsonCount(1, 'data.units')
        ->assertJsonCount(1, 'data.units.sub-'.subOf($version, 'M1.1')->id)
        ->assertJsonPath('data.units.sub-'.subOf($version, 'M1.1')->id.'.0.raw_value', '7.00')
        ->assertJsonPath('data.units.sub-'.subOf($version, 'M1.1')->id.'.0.round', 1);

    expect(array_keys($response->json('data.units')))->toBe([ScoreLevel::Sub->value.'-'.subOf($version, 'M1.1')->id]);
});

it('returns an empty units object before any score', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);

    expect(roundsOf($assessor, $assessment)->assertOk()->getContent())->toContain('"units":{}');
});

it('hides the completion rounds from a committee member, who evaluates blind', function (): void {
    $scenario = committeeScenario();

    roundsOf($scenario['assessors'][0], $scenario['requests'][0])->assertForbidden();
});

it('hides the completion rounds from the entity and from staff outside the request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($version, assessorUser());

    roundsOf(ownerOf($assessment->entity), $assessment)->assertForbidden();
    roundsOf(assessorUser(), $assessment)->assertNotFound();
    test()->getJson('/api/v1/assessments/999999/review/rounds')->assertNotFound();
});

it('erases a completed request with completion rounds on permanent deletion and keeps its scores', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    [$assessment] = resubmittedForRoundTwo($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ['M1.1' => '9']);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
    $entity = $assessment->entity;
    softDeleteWithOpenRequests($entity, []);

    switchTo(superAdmin())->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'حذف نهائي بناءً على طلب الجهة الرسمي وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->exists())->toBeFalse()
        ->and(currentScoreOf($assessment, $version, 'M1.1')->raw_value)->toBe('9.00')
        ->and(AssessmentScore::query()->where('assessment_id', $assessment->id)->whereNotNull('completion_request_id')->exists())->toBeFalse();
});
