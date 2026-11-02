<?php

declare(strict_types=1);

use App\Data\Criteria\CriteriaEditEffect;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaEditScopeRules;

it('needs no scope for the first publication and a scope for every later one', function (): void {
    $rules = new CriteriaEditScopeRules;

    expect($rules->scopeFor(false, null))->toBeNull()
        ->and($rules->scopeFor(false, CriteriaEditScope::All))->toBeNull()
        ->and($rules->scopeFor(true, CriteriaEditScope::ResetOpen))->toBe(CriteriaEditScope::ResetOpen)
        ->and(fn (): ?CriteriaEditScope => $rules->scopeFor(true, null))->toThrow(CriteriaVersionRejected::class);
});

it('requires a reason from the Super Admin only', function (): void {
    $rules = new CriteriaEditScopeRules;
    $officer = (new User)->forceFill(['is_super_admin' => false]);
    $superAdmin = (new User)->forceFill(['is_super_admin' => true]);

    $rules->assertReason($officer, null);
    $rules->assertReason($superAdmin, 'تصحيح عاجل بطلب من إدارة الجائزة.');

    expect(fn () => $rules->assertReason($superAdmin, null))->toThrow(CriteriaVersionRejected::class)
        ->and(fn () => $rules->assertReason($superAdmin, '   '))->toThrow(CriteriaVersionRejected::class);
});

it('describes the effect of each scope on open and closed assessments', function (CriteriaEditKind $kind, CriteriaEditScope $scope, array $expected): void {
    expect(new CriteriaEditEffect($kind, $scope)->toArray())->toMatchArray($expected);
})->with([
    'structural, all' => [CriteriaEditKind::Structural, CriteriaEditScope::All, [
        'reaches_closed_assessments' => false, 'rebinds_open_assessments' => true, 'keeps_scores_of_unchanged_items' => true,
        'clears_all_open_scores' => false, 'notifies_affected_entities_and_assessors' => false,
    ]],
    'structural, new only' => [CriteriaEditKind::Structural, CriteriaEditScope::NewOnly, [
        'reaches_closed_assessments' => false, 'rebinds_open_assessments' => false, 'keeps_scores_of_unchanged_items' => false,
        'clears_all_open_scores' => false, 'notifies_affected_entities_and_assessors' => false,
    ]],
    'structural, reset open' => [CriteriaEditKind::Structural, CriteriaEditScope::ResetOpen, [
        'reaches_closed_assessments' => false, 'rebinds_open_assessments' => true, 'keeps_scores_of_unchanged_items' => false,
        'clears_all_open_scores' => true, 'notifies_affected_entities_and_assessors' => true,
    ]],
    'text correction' => [CriteriaEditKind::TextCorrection, CriteriaEditScope::All, [
        'reaches_closed_assessments' => true, 'rebinds_open_assessments' => false, 'keeps_scores_of_unchanged_items' => true,
        'clears_all_open_scores' => false, 'notifies_affected_entities_and_assessors' => false,
    ]],
]);
