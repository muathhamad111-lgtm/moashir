<?php

declare(strict_types=1);

use App\Models\Criteria\CriteriaVersion;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lists the published edits and text corrections newest first with their versions, scope, impact and editor', function (): void {
    $first = publishedSelfCriteria();
    startedSelfAssessment(ownerOf(entityWithOwner()));
    $second = republishWithNewSubWeights('all');
    $officer = criteriaOfficer();

    switchTo($officer)->patchJson("/api/v1/criteria/versions/{$second->id}/labels", [
        'nodes' => [['type' => 'main', 'id' => mainOf($second, 'M1')->id, 'name' => ['ar' => 'القيادة المؤسسية', 'en' => 'Institutional Leadership']]],
    ])->assertOk();

    test()->getJson('/api/v1/criteria/edit-events')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.kind', 'text_correction')
        ->assertJsonPath('data.0.scope', 'all')
        ->assertJsonPath('data.0.from_version.id', $second->id)
        ->assertJsonPath('data.0.to_version', null)
        ->assertJsonPath('data.0.edited_by.name', $officer->name)
        ->assertJsonPath('data.1.kind', 'structural')
        ->assertJsonPath('data.1.scope', 'all')
        ->assertJsonPath('data.1.from_version.version_number', $first->version_number)
        ->assertJsonPath('data.1.to_version.version_number', $second->version_number)
        ->assertJsonPath('data.1.change_count', 2)
        ->assertJsonPath('data.1.score_affecting_count', 2)
        ->assertJsonPath('data.1.affected_open_count', 1)
        ->assertJsonPath('data.1.changes.0.code', 'M1');
});

it('shows the edit history to criteria readers only', function (): void {
    publishedSelfCriteria();

    switchTo(systemAdmin())->getJson('/api/v1/criteria/edit-events')->assertOk()->assertJsonPath('data', []);
    switchTo(assessorUser())->getJson('/api/v1/criteria/edit-events')->assertForbidden();
    switchTo(criteriaOfficer())->getJson('/api/v1/criteria/edit-events?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('creates a labelled draft from a chosen archived version', function (): void {
    $first = publishedSelfCriteria();
    republishWithNewSubWeights('new_only');

    $draft = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [
        'clone_from_version_id' => $first->id,
        'label' => ['ar' => 'مراجعة 2027', 'en' => '2027 review'],
    ])->assertCreated()
        ->assertJsonPath('data.cloned_from_version_id', $first->id)
        ->assertJsonPath('data.label.en', '2027 review')
        ->json('data.id');

    expect(mainOf(CriteriaVersion::query()->findOrFail($draft), 'M1')->weight->toDecimal())->toBe('1000.00');
});
