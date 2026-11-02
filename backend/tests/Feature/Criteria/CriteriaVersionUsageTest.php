<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('counts the assessments bound to each version in the list and on the record', function (): void {
    $first = publishedSelfCriteria();
    $closed = startedSelfAssessment(ownerOf(entityWithOwner()));
    $this->postJson("/api/v1/assessments/{$closed->id}/submit", ['scores' => selfScores($first, SELF_FULL_MARKS_OF_10)])->assertOk();
    startedSelfAssessment(ownerOf(entityWithOwner()));
    $frozenEntity = entityWithOwner();
    $frozen = startedSelfAssessment(ownerOf($frozenEntity));
    softDeleteWithOpenRequests($frozenEntity, [$frozen->request_number]);
    $second = republishWithNewSubWeights('new_only');
    startedSelfAssessment(ownerOf(entityWithOwner()));
    $draft = draftVersion(['version_number' => 3]);

    $listed = collect(switchTo(criteriaOfficer())->getJson('/api/v1/criteria/versions')->assertOk()->json('data'))->keyBy('id');

    expect($listed->map(static fn (array $version): array => [$version['status'], $version['bound_assessments'], $version['bound_open_assessments']])->all())->toEqual([
        $draft->id => ['draft', 0, 0],
        $second->id => ['published', 1, 1],
        $first->id => ['archived', 3, 1],
    ]);

    $this->getJson("/api/v1/criteria/versions/{$first->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $first->id)
        ->assertJsonPath('data.version_number', $first->version_number)
        ->assertJsonPath('data.bound_assessments', 3)
        ->assertJsonPath('data.bound_open_assessments', 1);
});

it('reports the open count that the next publication would re-bind', function (): void {
    $version = publishedSelfCriteria();
    startedSelfAssessment(ownerOf(entityWithOwner()));
    startedSelfAssessment(ownerOf(entityWithOwner()));

    switchTo(systemAdmin())->getJson("/api/v1/criteria/versions/{$version->id}")
        ->assertOk()
        ->assertJsonPath('data.bound_open_assessments', 2);

    republishWithNewSubWeights('all');

    $this->getJson("/api/v1/criteria/versions/{$version->id}")
        ->assertOk()
        ->assertJsonPath('data.bound_assessments', 0)
        ->assertJsonPath('data.bound_open_assessments', 0);
});

it('reads the counts of a whole page with a fixed number of queries', function (): void {
    publishedSelfCriteria();
    startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo(criteriaOfficer());

    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/criteria/versions')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $withOneVersion = $queriesFor();
    republishWithNewSubWeights('new_only');
    startedSelfAssessment(ownerOf(entityWithOwner()));
    draftVersion(['version_number' => 3]);
    switchTo(criteriaOfficer());

    expect($queriesFor())->toBe($withOneVersion);
});

it('keeps the counts behind the criteria permissions', function (RoleKey $role): void {
    $version = publishedSelfCriteria();
    signIn(staffUser($role));

    $this->getJson('/api/v1/criteria/versions')->assertForbidden();
    $this->getJson("/api/v1/criteria/versions/{$version->id}")->assertForbidden();
})->with([
    'assessor' => [RoleKey::Assessor],
    'finance officer' => [RoleKey::FinanceOfficer],
]);

it('keeps the counts away from entity accounts', function (): void {
    $version = publishedSelfCriteria();
    signIn(entityUser());

    $this->getJson("/api/v1/criteria/versions/{$version->id}")->assertForbidden();
});
