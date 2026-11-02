<?php

declare(strict_types=1);

use App\Enums\Criteria\ScaleKind;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCriteriaMove;
use App\Models\Criteria\CriteriaVersion;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const MOVE_RENAMED_SUB = ['ar' => 'نتائج المستفيدين وأثر البرامج', 'en' => 'Beneficiary Results and Programme Impact'];

const MOVE_ADDED_POINT = ['ar' => 'نشر تقرير سنوي عن تنفيذ الخطة الاستراتيجية.', 'en' => 'An annual report on the delivery of the strategic plan is published.'];

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array{level: string, code: string, label: array{ar: string, en: string}}
 */
function movedItem(CriteriaVersion $version, string $level, string $code): array
{
    $label = $level === 'sub' ? subOf($version, $code)->name : pointOf($version, $code)->description;

    return ['level' => $level, 'code' => $code, 'label' => $label->toArray()];
}

function republishWithAddedPointAndRenamedSub(string $scope): CriteriaVersion
{
    return republishCriteria($scope, static function (CriteriaVersion $draft): void {
        subOf($draft, 'M1.1')->forceFill(['weight' => Weight::of('500.00')])->save();
        $strategy = subOf($draft, 'M1.2');
        $strategy->forceFill(['weight' => Weight::of('500.00')])->save();
        pointIn($strategy, 'M1.2.3', '100.00', 3)->forceFill(['description' => MOVE_ADDED_POINT])->save();
        subOf($draft, 'M2.1')->forceFill(['name' => MOVE_RENAMED_SUB])->save();
    });
}

/**
 * @return array<string, mixed>|null
 */
function criteriaUpdateSeenBy(Assessment $assessment): ?array
{
    $update = switchTo(ownerOf($assessment->entity))->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()->json('data.criteria_update');

    return is_array($update) ? $update : null;
}

it('answers null while the draft was never moved to another criteria version', function (string $publication): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);

    if ($publication === 'new_only') {
        republishWithNewSubWeights('new_only');
    }

    if ($publication === 'text correction') {
        switchTo(criteriaOfficer())->patchJson("/api/v1/criteria/versions/{$version->id}/labels", [
            'nodes' => [['type' => 'sub', 'id' => subOf($version, 'M2.1')->id, 'name' => MOVE_RENAMED_SUB]],
        ])->assertOk();
    }

    switchTo(ownerOf($draft->entity))->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.criteria_update', null);

    expect(AssessmentCriteriaMove::query()->count())->toBe(0);
})->with(['nothing published', 'new_only', 'text correction']);

it('summarizes a move under scope "all" with kept, reset and added items and their current labels', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);

    $next = republishWithAddedPointAndRenamedSub('all');
    $move = AssessmentCriteriaMove::query()->sole();

    expect(criteriaUpdateSeenBy($draft))->toBe([
        'from_version_id' => $version->id,
        'from_version_number' => $version->version_number,
        'to_version_id' => $next->id,
        'to_version_number' => $next->version_number,
        'moved_at' => $move->created_at->toIso8601String(),
        'scope' => 'all',
        'trigger' => 'criteria_edit',
        'kept' => [movedItem($next, 'point', 'M1.2.1'), movedItem($next, 'point', 'M1.2.2'), movedItem($next, 'sub', 'M2.1')],
        'reset' => [movedItem($next, 'sub', 'M1.1')],
        'added' => [movedItem($next, 'point', 'M1.2.3')],
    ])
        ->and(criteriaUpdateSeenBy($draft)['kept'][2]['label'] ?? null)->toBe(MOVE_RENAMED_SUB)
        ->and(criteriaUpdateSeenBy($draft)['added'][0]['label'] ?? null)->toBe(MOVE_ADDED_POINT)
        ->and($move->kept)->toEqual([['level' => 'point', 'code' => 'M1.2.1'], ['level' => 'point', 'code' => 'M1.2.2'], ['level' => 'sub', 'code' => 'M2.1']])
        ->and($move->reset)->toEqual([['level' => 'sub', 'code' => 'M1.1']])
        ->and($move->added)->toEqual([['level' => 'point', 'code' => 'M1.2.3']]);
});

it('lists every scored item as reset under scope "reset_open"', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);

    $next = republishWithNewSubWeights('reset_open');

    $update = criteriaUpdateSeenBy($draft);

    expect($update)->toMatchArray([
        'from_version_id' => $version->id,
        'to_version_id' => $next->id,
        'scope' => 'reset_open',
        'trigger' => 'criteria_edit',
        'kept' => [],
        'added' => [],
    ])
        ->and(array_column($update['reset'] ?? [], 'code'))->toBe(['M1.1', 'M1.2.1', 'M1.2.2', 'M2.1']);
});

it('takes the label of a removed item from the version the draft left', function (): void {
    $version = publishedSelfCriteria(ScaleKind::Relative);
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    $this->putJson("/api/v1/assessments/{$draft->id}", ['scores' => selfScores($version, ['M1.1' => '60', 'M1.2.2' => '75'])])->assertOk();

    $next = republishCriteria('all', static function (CriteriaVersion $draftVersion): void {
        pointOf($draftVersion, 'M1.2.2')->delete();
    });

    expect(criteriaUpdateSeenBy($draft))->toMatchArray([
        'to_version_id' => $next->id,
        'kept' => [movedItem($next, 'sub', 'M1.1')],
        'reset' => [movedItem($version, 'point', 'M1.2.2')],
        'added' => [],
    ]);
});

it('summarizes the move of a frozen draft made when its entity is restored', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    $next = republishWithNewSubWeights('all');

    expect(AssessmentCriteriaMove::query()->count())->toBe(0);

    restoreEntity($entity);

    expect(criteriaUpdateSeenBy($draft))->toMatchArray([
        'from_version_id' => $version->id,
        'to_version_id' => $next->id,
        'scope' => 'all',
        'trigger' => 'entity_restore',
        'kept' => [movedItem($next, 'point', 'M1.2.1'), movedItem($next, 'point', 'M1.2.2'), movedItem($next, 'sub', 'M2.1')],
        'reset' => [movedItem($next, 'sub', 'M1.1')],
        'added' => [],
    ]);
});

it('summarizes the reset of a frozen draft restored after a "reset_open" publication', function (): void {
    $version = publishedSelfCriteria();
    $entity = entityWithOwner();
    $draft = scoredSelfDraft($version, $entity);
    softDeleteWithOpenRequests($entity, [$draft->request_number]);
    republishWithNewSubWeights('reset_open');

    restoreEntity($entity);

    $update = criteriaUpdateSeenBy($draft);

    expect($update)->toMatchArray(['scope' => 'reset_open', 'trigger' => 'entity_restore', 'kept' => [], 'added' => []])
        ->and(array_column($update['reset'] ?? [], 'code'))->toBe(['M1.1', 'M1.2.1', 'M1.2.2', 'M2.1']);
});

it('returns only the latest move', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);
    $second = republishWithNewSubWeights('all');
    $third = republishWithSmallerResults('reset_open');

    expect(AssessmentCriteriaMove::query()->where('assessment_id', $draft->id)->count())->toBe(2)
        ->and(criteriaUpdateSeenBy($draft))->toMatchArray([
            'from_version_id' => $second->id,
            'to_version_id' => $third->id,
            'scope' => 'reset_open',
            'kept' => [],
        ]);
});

it('shows staff readers the same summary under the record policy', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);
    republishWithNewSubWeights('all');
    $ownView = criteriaUpdateSeenBy($draft);

    expect($ownView)->toMatchArray(['scope' => 'all', 'trigger' => 'criteria_edit']);

    switchTo(systemAdmin())->getJson("/api/v1/assessments/{$draft->id}/form")
        ->assertOk()
        ->assertJsonPath('data.criteria_update', $ownView);

    switchTo(ownerOf(entityWithOwner()))->getJson("/api/v1/assessments/{$draft->id}/form")->assertNotFound();
    switchTo(staffUser(RoleKey::CriteriaOfficer))->getJson("/api/v1/assessments/{$draft->id}/form")->assertForbidden();
});

it('keeps the recorded moves append-only and erasable only with the entity', function (): void {
    $version = publishedSelfCriteria();
    $draft = scoredSelfDraft($version);
    republishWithNewSubWeights('all');
    $move = AssessmentCriteriaMove::query()->sole();

    expect(fn () => DB::transaction(fn () => DB::table('assessment_criteria_moves')->where('id', $move->id)->update(['scope' => 'reset_open'])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_criteria_moves')->where('id', $move->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and(fn () => $move->forceFill(['scope' => 'reset_open'])->save())
        ->toThrow(AppendOnlyViolation::class)
        ->and(fn () => DB::transaction(fn () => DB::table('assessment_criteria_moves')->insert([
            'assessment_id' => $draft->id, 'from_version_id' => $version->id, 'to_version_id' => $version->id, 'scope' => 'all',
            'triggered_by' => 'criteria_edit', 'kept' => '[]', 'reset' => '[]', 'added' => '[]', 'created_at' => now(),
        ])))->toThrow(QueryException::class, 'assessment_criteria_moves_versions_check');

    DB::transaction(static function () use ($draft): void {
        DB::select("SELECT set_config('moashir.entity_erasure', 'on', true)");
        DB::table('assessments')->where('id', $draft->id)->delete();
    });

    expect(AssessmentCriteriaMove::query()->count())->toBe(0);
});
