<?php

declare(strict_types=1);

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Assessments\AssessmentScore;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $body
 */
function previewOverride(User $actor, Assessment $assessment, array $body): TestResponse
{
    return switchTo($actor)->postJson("/api/v1/assessments/{$assessment->id}/scores/override/preview", $body);
}

/**
 * @return array{scores: int, audits: int, snapshots: int, transitions: int, certificates: int}
 */
function overrideFootprint(Assessment $assessment): array
{
    return [
        'scores' => AssessmentScore::query()->where('assessment_id', $assessment->id)->count(),
        'audits' => Audit::query()->count(),
        'snapshots' => AssessmentResultSnapshot::query()->where('assessment_id', $assessment->id)->count(),
        'transitions' => AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->count(),
        'certificates' => Certificate::query()->where('assessment_id', $assessment->id)->count(),
    ];
}

it('previews the result and level a Super Admin override would publish', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = completedAccreditedRequest($version, assessorUser());
    $policy = subOf($version, 'M1.1');
    $strategy = pointOf($version, 'M1.2.1');

    previewOverride(superAdmin(), $assessment, ['scores' => selfScores($version, ['M1.1' => '10', 'M1.2.1' => '9'])])->assertOk()
        ->assertJsonPath('data.before', ['percent' => '77.00', 'level' => 'silver'])
        ->assertJsonPath('data.after', ['percent' => '85.00', 'level' => 'gold'])
        ->assertJsonPath('data.changes', [['code' => 'M1.1', 'field' => "sub-{$policy->id}", 'before' => '8.00', 'after' => '10.00']]);

    previewOverride(superAdmin(), $assessment, ['scores' => selfScores($version, ['M1.2.1' => '9'])])->assertOk()
        ->assertJsonPath('data.after', ['percent' => '77.00', 'level' => 'silver'])
        ->assertJsonPath('data.changes', []);

    previewOverride(superAdmin(), $assessment, ['scores' => selfScores($version, ['M1.1' => '0', 'M1.2.1' => '0', 'M1.2.2' => '0', 'M2.1' => '0'])])->assertOk()
        ->assertJsonPath('data.after', ['percent' => '0.00', 'level' => null])
        ->assertJsonCount(4, 'data.changes')
        ->assertJsonPath('data.changes.1', ['code' => 'M1.2.1', 'field' => "point-{$strategy->id}", 'before' => '9.00', 'after' => '0.00']);
});

it('persists nothing and audits nothing when previewing an override', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = completedAccreditedRequest($version, assessorUser());
    $admin = superAdmin();
    switchTo($admin);
    $before = overrideFootprint($assessment);
    $updatedAt = $assessment->refresh()->updated_at?->toIso8601String();

    previewOverride($admin, $assessment, ['scores' => selfScores($version, ['M1.1' => '10'])])->assertOk();

    expect(overrideFootprint($assessment))->toBe($before)
        ->and($assessment->refresh()->updated_at?->toIso8601String())->toBe($updatedAt)
        ->and(AssessmentScore::query()->where('assessment_id', $assessment->id)->where('sub_criterion_id', subOf($version, 'M1.1')->id)->current()->sole()->raw_value)->toBe('8.00');
});

it('refuses the override preview to everyone but the Super Admin', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = completedAccreditedRequest($version, $assessor);
    $body = ['scores' => selfScores($version, ['M1.1' => '10'])];

    previewOverride(systemAdmin(), $assessment, $body)->assertForbidden();
    previewOverride(requestFollower(), $assessment, $body)->assertForbidden();
    previewOverride($assessor, $assessment, $body)->assertForbidden();
    previewOverride(ownerOf($assessment->entity), $assessment, $body)->assertForbidden();
    previewOverride(assessorUser(), $assessment, $body)->assertNotFound();
});

it('refuses the override preview while the request exposes no result', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($version, assessorUser());

    previewOverride(superAdmin(), $assessment, ['scores' => selfScores($version, ['M1.1' => '10'])])
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal');
});

it('validates the scores of an override preview without asking for a reason', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = completedAccreditedRequest($version, assessorUser());
    $admin = superAdmin();
    $policyId = (string) subOf($version, 'M1.1')->id;

    previewOverride($admin, $assessment, [])->assertUnprocessable()->assertJsonValidationErrors(['scores']);
    previewOverride($admin, $assessment, ['scores' => ['subs' => [$policyId => null]]])->assertUnprocessable()->assertJsonValidationErrors(["scores.subs.{$policyId}"]);
    previewOverride($admin, $assessment, ['scores' => ['subs' => [$policyId => '10.555']]])->assertUnprocessable()->assertJsonValidationErrors(["scores.subs.{$policyId}"]);
    previewOverride($admin, $assessment, ['scores' => ['subs' => [$policyId => '11']]])->assertUnprocessable()->assertJsonPath('code', 'score.out_of_range');
    previewOverride($admin, $assessment, ['scores' => ['subs' => ['999999' => '5']]])->assertUnprocessable();
});
