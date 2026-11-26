<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;

it('returns the whole draft tree, inactive nodes included, in the node endpoint shapes', function (): void {
    $draft = balancedDraft();
    pointOf($draft, 'M1.1.2')->forceFill(['is_active' => false])->save();
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/versions/'.$draft->id.'/tree')
        ->assertOk()
        ->assertJsonPath('data.version.id', $draft->id)
        ->assertJsonPath('data.version.status', 'draft')
        ->assertJsonPath('data.active_weight_total', '1000.00')
        ->assertJsonPath('data.mains.0.code', 'M1')
        ->assertJsonPath('data.mains.0.weight_balanced', true)
        ->assertJsonPath('data.mains.0.subs.0.code', 'M1.1')
        ->assertJsonPath('data.mains.0.subs.0.evaluation_type', 'guiding_points')
        ->assertJsonPath('data.mains.0.subs.0.points.1.code', 'M1.1.2')
        ->assertJsonPath('data.mains.0.subs.0.points.1.active', false)
        ->assertJsonPath('data.mains.0.subs.1.evaluation_type', 'direct')
        ->assertJsonPath('data.mains.0.subs.1.points', []);
});

it('serves a published version to the System Administrator', function (): void {
    $published = publishVersion(balancedDraft());
    signIn(systemAdmin());

    $this->getJson('/api/v1/criteria/versions/'.$published->id.'/tree')
        ->assertOk()
        ->assertJsonPath('data.version.status', 'published')
        ->assertJsonCount(1, 'data.mains');
});

it('denies the tree to roles without criteria.view', function (RoleKey $role): void {
    $draft = balancedDraft();
    signIn(staffUser($role));

    $this->getJson('/api/v1/criteria/versions/'.$draft->id.'/tree')->assertForbidden();
})->with([
    'assessor' => [RoleKey::Assessor],
    'request follower' => [RoleKey::RequestFollower],
]);

it('answers 404 for an unknown version', function (): void {
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/criteria/versions/999999/tree')->assertNotFound();
});
