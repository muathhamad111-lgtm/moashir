<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\CycleMemberRole;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function assessmentsOf(User $viewer, string $query = ''): TestResponse
{
    return switchTo($viewer)->getJson('/api/v1/assessments'.$query)->assertOk();
}

/**
 * @return list<int>
 */
function listedIds(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('id')->sort()->values()->all();
}

/**
 * @return array{owner: User, drafts: list<int>, results: list<int>}
 */
function entityWithEveryTab(): array
{
    $version = publishedAccreditedCriteria();
    $completed = completedAccreditedRequest($version, assessorUser());
    $owner = ownerOf($completed->entity);
    $selfResult = scoredSelfDraft($version, $completed->entity);
    switchTo($owner)->postJson("/api/v1/assessments/{$selfResult->id}/submit")->assertOk()->assertJsonPath('data.status', 'result_available');
    $selfDraft = startedSelfAssessment($owner);
    $accreditedDraft = openedAccreditedRequest($completed->entity);

    return [
        'owner' => $owner,
        'drafts' => collect([$selfDraft->id, $accreditedDraft->id])->sort()->values()->all(),
        'results' => collect([$completed->id, $selfResult->id])->sort()->values()->all(),
    ];
}

it('splits the own requests into open ones and published results and counts both regardless of those filters', function (): void {
    ['owner' => $owner, 'drafts' => $drafts, 'results' => $results] = entityWithEveryTab();
    $counts = ['total' => 4, 'open' => 2, 'with_result' => 2];

    expect(listedIds(assessmentsOf($owner, '?filter[is_open]=true')->assertJsonPath('meta.counts', $counts)))->toBe($drafts)
        ->and(listedIds(assessmentsOf($owner, '?filter[has_result]=true')->assertJsonPath('meta.counts', $counts)))->toBe($results)
        ->and(listedIds(assessmentsOf($owner, '?filter[is_open]=false&filter[has_result]=false')))->toBe([])
        ->and(listedIds(assessmentsOf($owner, '?filter[is_open]=false')))->toBe($results);

    assessmentsOf($owner, '?filter[path]=self&filter[status]=draft')->assertJsonPath('meta.counts', ['total' => 2, 'open' => 1, 'with_result' => 1]);
});

it('keeps a masked advanced outcome open and without a result until the announcement', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);
    $assessment = initiallyAssessedRequest($cycle, $version, assessorUser());
    $officer = filteringOfficer();
    addCycleMember($cycle, $officer, CycleMemberRole::FilteringOfficer);
    closeCycleSubmissions($cycle);
    switchTo($officer)->postJson("/api/v1/award/cycles/{$cycle->id}/filtering/{$assessment->id}/disqualify", ['reason' => DISQUALIFY_REASON])->assertOk();
    $owner = ownerOf($assessment->entity);

    assessmentsOf($owner, '?filter[is_open]=true')
        ->assertJsonPath('data.0.id', $assessment->id)
        ->assertJsonPath('data.0.is_open', true)
        ->assertJsonPath('data.0.result', null)
        ->assertJsonPath('meta.counts', ['total' => 1, 'open' => 1, 'with_result' => 0]);
    expect(listedIds(assessmentsOf($owner, '?filter[has_result]=true')))->toBe([]);

    $cycle->forceFill(['announced_at' => Date::now()])->save();

    assessmentsOf($owner, '?filter[has_result]=true')
        ->assertJsonPath('data.0.id', $assessment->id)
        ->assertJsonPath('data.0.is_open', false)
        ->assertJsonPath('meta.counts', ['total' => 1, 'open' => 0, 'with_result' => 1]);
});

it('counts within the record scope of the staff viewer', function (): void {
    $version = publishedAccreditedCriteria();
    $completed = completedAccreditedRequest($version, assessorUser());
    $cycle = openAwardCycle($version);
    $advanced = submittedAdvancedRequest($cycle, $version);

    $global = assessmentsOf(systemAdmin())->json('meta.counts');
    $accreditedOnly = assessmentsOf(followerOfPaths(AssessmentPath::Accredited))->json('meta.counts');

    expect($global['total'] ?? null)->toBe(Assessment::query()->count())
        ->and($accreditedOnly['total'] ?? null)->toBe(Assessment::query()->where('path', '<>', AssessmentPath::Advanced)->count())
        ->and($accreditedOnly['with_result'] ?? null)->toBe(1)
        ->and(listedIds(assessmentsOf(followerOfPaths(AssessmentPath::Accredited), '?filter[has_result]=true')))->toBe([$completed->id])
        ->and(listedIds(assessmentsOf(systemAdmin(), '?filter[path]=advanced&filter[is_open]=true')))->toBe([$advanced->id]);
});

it('refuses a filter value that is not true or false', function (string $query, string $field): void {
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/assessments?'.$query)->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);
})->with([
    'open' => ['filter[is_open]=yes', 'filter.is_open'],
    'result' => ['filter[has_result]=1', 'filter.has_result'],
]);

it('keeps the list for signed-in accounts only', function (): void {
    asGuest()->getJson('/api/v1/assessments?filter[is_open]=true')->assertUnauthorized();
});
