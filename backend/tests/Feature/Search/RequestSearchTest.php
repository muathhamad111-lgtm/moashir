<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;

it('finds self assessment requests by number for the global readers', function (RoleKey $role): void {
    publishedSelfCriteria();
    $entity = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'));
    $draft = startedSelfAssessment(ownerOf($entity));
    startedSelfAssessment(ownerOf(entityWithOwner(static fn ($factory) => $factory->named('مؤسسة النماء الأهلية'))));
    switchTo(staffUser($role));

    $this->getJson('/api/v1/search?q='.urlencode(strtolower($draft->request_number)))
        ->assertOk()
        ->assertJsonPath('data.1.type', 'requests')
        ->assertJsonPath('data.1.total', 1)
        ->assertJsonPath('data.1.items.0', [
            'id' => $draft->id,
            'number' => $draft->request_number,
            'path' => 'self',
            'path_label' => ['ar' => 'التقييم الذاتي', 'en' => 'Self assessment'],
            'status' => 'draft',
            'is_frozen' => false,
            'entity' => [
                'id' => $entity->id,
                'reference' => $entity->reference(),
                'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
                'status' => 'active',
            ],
        ]);
})->with([RoleKey::RequestFollower, RoleKey::SystemAdmin]);

it('finds requests by the normalised Arabic name of their entity', function (): void {
    publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية'))));
    startedSelfAssessment(ownerOf(entityWithOwner(static fn ($factory) => $factory->named('مؤسسة النماء الأهلية'))));
    switchTo(staffUser(RoleKey::RequestFollower));

    $this->getJson('/api/v1/search?q='.urlencode('الامل'))
        ->assertOk()
        ->assertJsonPath('data.1.total', 1)
        ->assertJsonPath('data.1.items.0.id', $draft->id);
});

it('counts every match but returns at most the requested number of items', function (): void {
    publishedSelfCriteria();
    startedSelfAssessment(ownerOf(entityWithOwner()));
    startedSelfAssessment(ownerOf(entityWithOwner()));
    startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo(staffUser(RoleKey::RequestFollower));

    $this->getJson('/api/v1/search?q=slf-&limit=2')
        ->assertOk()
        ->assertJsonPath('data.1.total', 3)
        ->assertJsonCount(2, 'data.1.items');
});

it('keeps the requests group empty for an assessor without assigned requests', function (): void {
    publishedSelfCriteria();
    $draft = startedSelfAssessment(ownerOf(entityWithOwner()));
    switchTo(staffUser(RoleKey::Assessor));

    $this->getJson('/api/v1/search?q='.urlencode($draft->request_number))
        ->assertOk()
        ->assertJsonPath('data.1.type', 'requests')
        ->assertJsonPath('data.1.total', 0);
});
