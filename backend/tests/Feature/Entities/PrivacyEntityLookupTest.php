<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Entities\Entity;
use Illuminate\Support\Facades\Date;

const LOOKUP_ENDPOINT = '/api/v1/privacy/entities';

function erasedEntity(): Entity
{
    $entity = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل المحذوفة', 'Al-Amal Erased Association'));
    $entity->delete();
    $entity->forceFill(['anonymized_at' => Date::now()])->save();

    return $entity;
}

it('serves the entity picker to a holder of the privacy permission without entities.view', function (): void {
    grantPrivacyToContentOfficers();
    $entity = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'));
    $officer = staffUser(RoleKey::ContentOfficer);
    signIn($officer);

    $this->getJson('/api/v1/entities')->assertForbidden();

    $response = $this->getJson(LOOKUP_ENDPOINT.'?search='.urlencode('الامل'))
        ->assertOk()
        ->assertJsonPath('meta.limit', 10)
        ->assertJsonPath('data', [[
            'id' => $entity->id,
            'reference' => $entity->reference(),
            'name' => ['ar' => 'جمعية الأمل الخيرية', 'en' => 'Al-Amal Charitable Association'],
            'is_deleted' => false,
        ]]);

    expect($response->getContent())->not->toContain(ownerOf($entity)->email);
});

it('finds entities by their Arabic or English name or their official e-mail', function (string $term): void {
    $entity = entityWithOwner(static fn ($factory) => $factory->named('مؤسسة النماء الأهلية', 'Al-Namaa Civil Foundation'));
    entityWithOwner(static fn ($factory) => $factory->named('وقف الخير التنموي', 'Al-Khair Development Waqf'));
    $term = $term === 'e-mail' ? ownerOf($entity)->email : $term;
    signIn(systemAdmin());

    $this->getJson(LOOKUP_ENDPOINT.'?search='.urlencode($term))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $entity->id);
})->with(['النماء', 'namaa', 'e-mail']);

it('lists deleted entities, which data-subject requests may concern, but never erased ones', function (): void {
    $deleted = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية'));
    softDeleteWithOpenRequests($deleted, []);
    erasedEntity();
    switchTo(systemAdmin());

    $this->getJson(LOOKUP_ENDPOINT.'?search='.urlencode('الأمل'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $deleted->id)
        ->assertJsonPath('data.0.is_deleted', true);
});

it('finds an entity by its reference', function (string $format): void {
    entityWithOwner(static fn ($factory) => $factory->named('جمعية النور الأهلية'));
    $entity = entityWithOwner(static fn ($factory) => $factory->named('جمعية البر الأهلية'));
    signIn(systemAdmin());
    $term = match ($format) {
        'full' => $entity->reference(),
        'lower case' => mb_strtolower($entity->reference()),
        'without dash' => str_replace('-', '', $entity->reference()),
    };

    $this->getJson(LOOKUP_ENDPOINT.'?search='.urlencode($term))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $entity->id)
        ->assertJsonPath('data.0.reference', $entity->reference());
})->with(['full', 'lower case', 'without dash']);

it('lists the entities by name without a search term, within the limit', function (): void {
    entityWithOwner(static fn ($factory) => $factory->named('جمعية النور الأهلية'));
    entityWithOwner(static fn ($factory) => $factory->named('جمعية البر الأهلية'));
    entityWithOwner(static fn ($factory) => $factory->named('جمعية الخير الأهلية'));
    signIn(systemAdmin());

    $this->getJson(LOOKUP_ENDPOINT.'?limit=2')
        ->assertOk()
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name.ar', 'جمعية البر الأهلية')
        ->assertJsonPath('data.1.name.ar', 'جمعية الخير الأهلية');
});

it('validates the lookup input', function (string $query, string $field): void {
    signIn(systemAdmin());

    $this->getJson(LOOKUP_ENDPOINT.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'limit zero' => ['limit=0', 'limit'],
    'limit above 20' => ['limit=21', 'limit'],
    'limit not a number' => ['limit=all', 'limit'],
    'search too long' => ['search='.str_repeat('a', 101), 'search'],
]);

it('keeps the entity picker behind the privacy permission', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->getJson(LOOKUP_ENDPOINT)->assertForbidden()->assertJsonPath('code', 'forbidden');
})->with([RoleKey::RequestFollower, RoleKey::FinanceOfficer, RoleKey::ContentOfficer]);

it('denies the entity picker to entity accounts and guests', function (): void {
    $this->getJson(LOOKUP_ENDPOINT)->assertUnauthorized();

    signIn(entityUser());

    $this->getJson(LOOKUP_ENDPOINT)->assertForbidden();
});

it('rate limits the entity picker per user', function (): void {
    signIn(systemAdmin());

    foreach (range(1, 60) as $attempt) {
        $this->getJson(LOOKUP_ENDPOINT.'?search=ab')->assertOk();
    }

    $this->getJson(LOOKUP_ENDPOINT.'?search=ab')->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});
