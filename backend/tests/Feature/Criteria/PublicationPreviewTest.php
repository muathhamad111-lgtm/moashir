<?php

declare(strict_types=1);

use App\Models\Criteria\CriteriaVersion;
use App\Support\ValueObjects\Weight;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function previewDraftOf(Closure $editDraft): CriteriaVersion
{
    $draftId = switchTo(criteriaOfficer())->postJson('/api/v1/criteria/versions', [])->assertCreated()->json('data.id');
    $draft = CriteriaVersion::query()->findOrFail($draftId);
    $editDraft($draft);

    return $draft;
}

it('lists the changes of a draft against the published version with their labels and score impact', function (): void {
    $published = publishedSelfCriteria();
    $draft = previewDraftOf(static function (CriteriaVersion $draft): void {
        subOf($draft, 'M1.1')->forceFill(['weight' => Weight::of('500.00')])->save();
        mainOf($draft, 'M1')->forceFill(['weight' => Weight::of('900.00')])->save();
        subOf($draft, 'M2.1')->forceFill(['name' => ['ar' => 'نتائج المستفيدين المحدثة', 'en' => 'Updated beneficiary results']])->save();
    });

    switchTo(criteriaOfficer())->getJson("/api/v1/criteria/versions/{$draft->id}/changes")->assertOk()
        ->assertJsonPath('data.replaces.id', $published->id)
        ->assertJsonPath('data.replaces.version_number', $published->version_number)
        ->assertJsonPath('data.change_count', 3)
        ->assertJsonPath('data.score_affecting_count', 2)
        ->assertJsonPath('data.changes.0.node_type', 'main')
        ->assertJsonPath('data.changes.0.code', 'M1')
        ->assertJsonPath('data.changes.0.fields', ['weight'])
        ->assertJsonPath('data.changes.0.before.weight', '1000.00')
        ->assertJsonPath('data.changes.0.after.weight', '900.00')
        ->assertJsonPath('data.changes.0.score_affecting', true)
        ->assertJsonPath('data.changes.2.code', 'M2.1')
        ->assertJsonPath('data.changes.2.fields', ['name'])
        ->assertJsonPath('data.changes.2.score_affecting', false)
        ->assertJsonPath('data.changes.2.label.en', 'Updated beneficiary results');
});

it('counts the open self and accredited requests a publication would move, but never advanced ones', function (): void {
    $published = publishedAccreditedCriteria();
    startedSelfAssessment(ownerOf(entityWithOwner()));
    startedSelfAssessment(ownerOf(entityWithOwner()));
    submittedAdvancedRequest(openAwardCycle($published), $published);
    $draft = previewDraftOf(static fn (CriteriaVersion $draft): null => null);

    switchTo(criteriaOfficer())->getJson("/api/v1/criteria/versions/{$draft->id}/changes")->assertOk()
        ->assertJsonPath('data.change_count', 0)
        ->assertJsonPath('data.rebound_open_assessments', [
            ['scope' => 'all', 'count' => 2],
            ['scope' => 'new_only', 'count' => 0],
            ['scope' => 'reset_open', 'count' => 2],
        ]);
});

it('answers without a replaced version before the first publication', function (): void {
    $draft = draftVersion();

    switchTo(criteriaOfficer())->getJson("/api/v1/criteria/versions/{$draft->id}/changes")->assertOk()
        ->assertJsonPath('data.replaces', null)
        ->assertJsonPath('data.changes', [])
        ->assertJsonPath('data.rebound_open_assessments.0', ['scope' => 'all', 'count' => 0]);
});

it('previews only drafts and only for criteria readers', function (): void {
    $published = publishedSelfCriteria();
    $draft = previewDraftOf(static fn (CriteriaVersion $draft): null => null);

    switchTo(criteriaOfficer())->getJson("/api/v1/criteria/versions/{$published->id}/changes")->assertConflict()
        ->assertJsonPath('code', 'criteria.version_not_draft');
    switchTo(assessorUser())->getJson("/api/v1/criteria/versions/{$draft->id}/changes")->assertForbidden();
    switchTo(systemAdmin())->getJson("/api/v1/criteria/versions/{$draft->id}/changes")->assertOk();
});
