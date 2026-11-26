<?php

declare(strict_types=1);

use App\Enums\Criteria\ScoringType;
use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * The accredited test tree with its two direct sub-criteria (M1.1, M2.1) judged achieved or not, and the indicators
 * of M1.2 scored out of 10.
 */
function achievementAccreditedCriteria(): CriteriaVersion
{
    $draft = draftAccreditedCriteria();

    foreach (['M1.1', 'M2.1'] as $code) {
        subOf($draft, $code)->forceFill(['scoring_type' => ScoringType::Achievement])->save();
    }

    foreach (['M1.2.1', 'M1.2.2'] as $code) {
        pointOf($draft, $code)->forceFill(['scoring_type' => ScoringType::Score])->save();
    }

    return publishAuthoredVersion($draft->refresh());
}

it('gives each scored unit its scoring type in the accredited result breakdown', function (): void {
    $version = achievementAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ['M1.1' => '1', 'M1.2.1' => '9', 'M1.2.2' => '6', 'M2.1' => '0'])])->assertOk();
    test()->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => accreditedFeedback($version)])->assertOk();
    test()->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();

    switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/result")->assertOk()
        ->assertJsonPath('data.sub.0.code', 'M1.1')
        ->assertJsonPath('data.sub.0.scoring_type', 'achievement')
        ->assertJsonPath('data.sub.0.raw_value', '1.00')
        ->assertJsonPath('data.sub.2.code', 'M2.1')
        ->assertJsonPath('data.sub.2.scoring_type', 'achievement')
        ->assertJsonPath('data.sub.2.raw_value', '0.00')
        ->assertJsonPath('data.points.0.code', 'M1.2.1')
        ->assertJsonPath('data.points.0.scoring_type', 'score')
        ->assertJsonPath('data.points.0.raw_value', '9.00');
});
