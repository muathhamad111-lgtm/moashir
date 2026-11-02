<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;

it('finds entities by normalized Arabic name for staff with full entity access', function (string $term): void {
    $target = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'));
    entityWithOwner(static fn ($factory) => $factory->named('مؤسسة النماء الأهلية', 'Al-Namaa Civil Foundation'));
    signIn(systemAdmin());

    $this->getJson('/api/v1/search?q='.urlencode($term))
        ->assertOk()
        ->assertJsonPath('data.0.type', 'entities')
        ->assertJsonPath('data.0.total', 1)
        ->assertJsonPath('data.0.items.0', [
            'id' => $target->id,
            'reference' => $target->reference(),
            'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
            'status' => 'active',
        ]);
})->with([
    'exact' => ['الأمل'],
    'without hamza' => ['الامل'],
    'with diacritics' => ['الأَمَل'],
    'with tatweel' => ['الأمـــل'],
    'english' => ['AMAL CHARITABLE'],
]);

it('finds an entity by its official e-mail', function (): void {
    $target = entityWithOwner();
    ownerOf($target)->forceFill(['email' => 'contact@alnamaa.example.sa'])->save();
    signIn(systemAdmin());

    $this->getJson('/api/v1/search?q=contact@alnamaa')->assertOk()->assertJsonPath('data.0.items.0.id', $target->id);
});

it('never returns deleted or erased entities', function (): void {
    $deleted = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية'));
    $deleted->delete();
    signIn(systemAdmin());

    $this->getJson('/api/v1/search?q='.urlencode('الأمل'))->assertOk()->assertJsonPath('data.0.total', 0);
});

it('keeps the entities group empty for roles scoped to their own assignments', function (RoleKey $role): void {
    entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية'));
    signIn(staffUser($role));

    $this->getJson('/api/v1/search?q='.urlencode('الأمل'))
        ->assertOk()
        ->assertJsonPath('data.0.type', 'entities')
        ->assertJsonPath('data.0.total', 0);
})->with([RoleKey::Assessor]);

it('lets the finance officer find entities by name', function (): void {
    entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية'));
    signIn(staffUser(RoleKey::FinanceOfficer));

    $this->getJson('/api/v1/search?q='.urlencode('الأمل'))->assertOk()->assertJsonPath('data.0.total', 1);
});

it('does not offer the entities group to entity users', function (): void {
    signIn(ownerOf(entityWithOwner()));

    $this->getJson('/api/v1/search?q='.urlencode('الأمل'))->assertForbidden();
});
