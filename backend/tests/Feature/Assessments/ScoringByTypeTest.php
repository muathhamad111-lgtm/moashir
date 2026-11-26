<?php

declare(strict_types=1);

use App\Enums\Criteria\ScaleKind;
use App\Enums\Criteria\ScoringType;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Support\ValueObjects\Weight;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * One sub-criterion scored directly as a percentage, and one whose three indicators use the three types.
 */
function mixedTypeCriteria(): CriteriaVersion
{
    $version = draftVersion();
    $main = mainIn($version, 'M1', '400.00');
    directSubIn($main, 'M1.1', '100.00')->forceFill(['scoring_type' => ScoringType::Relative])->save();
    $sub = subIn($main, 'M1.2', '300.00', 2);
    pointIn($sub, 'M1.2.1', '100.00')->forceFill(['scoring_type' => ScoringType::Achievement])->save();
    pointIn($sub, 'M1.2.2', '100.00', 2)->forceFill(['scoring_type' => ScoringType::Relative])->save();
    pointIn($sub, 'M1.2.3', '100.00', 3)->forceFill(['scoring_type' => ScoringType::Score])->save();

    return publishAuthoredVersion($version->refresh());
}

it('scores each unit of a new request with its own type', function (): void {
    $version = mixedTypeCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $achieved = pointOf($version, 'M1.2.1');

    expect($draft->scale_kind)->toBeNull();

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '80', 'M1.2.1' => '1', 'M1.2.2' => '40', 'M1.2.3' => '7'])])
        ->assertOk()
        ->assertJsonPath("data.scores.points.{$achieved->id}", ['raw_value' => '1.00', 'percent' => '100.00']);
    $this->getJson("/api/v1/assessments/{$draft->id}/form")->assertOk()
        ->assertJsonPath('data.scale', null)
        ->assertJsonPath('data.criteria.mains.0.subs.1.points.0.scoring_type', 'achievement');

    $this->postJson("/api/v1/assessments/{$draft->id}/submit", ['scores' => selfScores($version, ['M1.1' => '80', 'M1.2.1' => '1', 'M1.2.2' => '40', 'M1.2.3' => '7'])])
        ->assertOk();

    $snapshot = Assessment::query()->findOrFail($draft->id)->currentResult;
    expect($snapshot?->percent->toDisplay())->toBe('72.50')
        ->and($snapshot?->breakdown['mains'][0]['subs'][1]['points'][0]['scoring_type'] ?? null)->toBe('achievement');
});

it('refuses a value its unit type does not allow', function (string $code, string $raw): void {
    $version = mixedTypeCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, [$code => $raw])])
        ->assertUnprocessable();
})->with([
    'half achieved' => ['M1.2.1', '0.5'],
    'achievement above one' => ['M1.2.1', '2'],
    'score above ten' => ['M1.2.3', '11'],
    'percentage above one hundred' => ['M1.1', '100.5'],
]);

it('keeps scoring a request stamped before the change on its own scale', function (): void {
    $version = mixedTypeCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $draft->forceFill(['scale_kind' => ScaleKind::OutOfTen])->save();

    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.2.1' => '7.5'])])
        ->assertOk()
        ->assertJsonPath('data.scale', ['kind' => 'of_10', 'maximum' => '10.00']);
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '80'])])
        ->assertUnprocessable();
});

it('clears the stamp of a request that follows a criteria edit and keeps its carried scores', function (): void {
    $version = publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $draft->forceFill(['scale_kind' => ScaleKind::OutOfTen])->save();
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.2.1' => '9'])])->assertOk();

    $next = republishCriteria('all', static function (CriteriaVersion $draftVersion): void {
        subOf($draftVersion, 'M2.1')->forceFill(['weight' => Weight::of('400.00')])->save();
        mainOf($draftVersion, 'M2')->forceFill(['weight' => Weight::of('400.00')])->save();
    });
    $rebound = Assessment::query()->findOrFail($draft->id);

    expect($rebound->criteria_version_id)->toBe($next->id)
        ->and($rebound->scale_kind)->toBeNull();
    switchTo(ownerOf($rebound->entity))->getJson("/api/v1/assessments/{$draft->id}/form")->assertOk()
        ->assertJsonPath('data.scale', null)
        ->assertJsonPath('data.scores.points.'.pointOf($next, 'M1.2.1')->id.'.raw_value', '9.00');
});
