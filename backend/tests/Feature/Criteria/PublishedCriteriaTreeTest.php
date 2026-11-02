<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\PublishedCriteriaTree;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('serves the published tree to every role that reads criteria for assessments', function (Closure $reader): void {
    installedSeedCriteria();
    signIn($reader());

    $this->getJson('/api/v1/assessments/criteria')
        ->assertOk()
        ->assertJsonPath('data.version_number', 1)
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.scale_kind', 'of_10')
        ->assertJsonPath('data.total_weight', '8200.00')
        ->assertJsonCount(9, 'data.mains')
        ->assertJsonPath('data.mains.0.code', 'M1')
        ->assertJsonPath('data.mains.0.subs.0.evaluation_type', 'guiding_points')
        ->assertJsonPath('data.mains.0.subs.0.points.0.weight', '133.34')
        ->assertJsonPath('data.mains.0.subs.0.attachments.0.required', true)
        ->assertJsonPath('data.mains.0.subs.2.evaluation_type', 'direct')
        ->assertJsonPath('data.mains.0.subs.2.points', []);
})->with([
    'entity' => [entityUser(...)],
    'assessor' => [static fn (): User => staffUser(RoleKey::Assessor)],
    'content officer' => [static fn (): User => staffUser(RoleKey::ContentOfficer)],
    'criteria officer' => [criteriaOfficer(...)],
]);

it('leaves inactive items out of the published tree', function (): void {
    $draft = balancedDraft();
    $extra = mainIn($draft, 'M2', '100.00', 2);
    directSubIn($extra, 'M2.1', '100.00');
    $extra->forceFill(['is_active' => false])->save();
    publishVersion($draft);
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/assessments/criteria')
        ->assertOk()
        ->assertJsonCount(1, 'data.mains')
        ->assertJsonPath('data.total_weight', '1000.00');
});

it('denies the published tree to roles without assessment.criteria.view', function (): void {
    installedSeedCriteria();
    signIn(staffUser(RoleKey::FinanceOfficer));

    $this->getJson('/api/v1/assessments/criteria')->assertForbidden();
});

it('answers 404 when no version is published', function (): void {
    balancedDraft();
    signIn(criteriaOfficer());

    $this->getJson('/api/v1/assessments/criteria')->assertNotFound()->assertJsonPath('code', 'criteria.no_published_version');
});

it('serves an archived version only to criteria staff', function (): void {
    $archived = archiveVersion(publishVersion(balancedDraft()));
    $draft = draftVersion();

    signIn(criteriaOfficer());
    $this->getJson('/api/v1/assessments/criteria?version='.$archived->id)->assertOk()->assertJsonPath('data.status', 'archived');
    $this->getJson('/api/v1/assessments/criteria?version='.$draft->id)->assertNotFound()->assertJsonPath('code', 'criteria.no_published_version');
    $this->getJson('/api/v1/assessments/criteria?version=abc')->assertUnprocessable()->assertJsonValidationErrors('version');
});

it('refuses an explicit version to entity users', function (): void {
    $archived = archiveVersion(publishVersion(balancedDraft()));
    signIn(entityUser());

    $this->getJson('/api/v1/assessments/criteria?version='.$archived->id)->assertForbidden();
});

it('serves the published tree from the cache', function (): void {
    installedSeedCriteria();
    signIn(criteriaOfficer());
    $this->getJson('/api/v1/assessments/criteria')->assertOk();

    expect(Cache::has(PublishedCriteriaTree::CACHE_KEY))->toBeTrue();

    DB::enableQueryLog();
    $this->getJson('/api/v1/assessments/criteria')->assertOk()->assertJsonCount(9, 'data.mains');
    $criteriaQueries = array_filter(DB::getQueryLog(), static fn (array $entry): bool => str_contains((string) $entry['query'], 'criteria'));

    expect($criteriaQueries)->toBe([]);
});

it('publishes the excellence model to anonymous visitors without weights', function (): void {
    installedSeedCriteria();

    $response = $this->getJson('/api/v1/content/model-criteria')
        ->assertOk()
        ->assertJsonCount(9, 'data')
        ->assertJsonPath('data.0.code', 'M1')
        ->assertJsonPath('data.0.group', 'enablers')
        ->assertJsonPath('data.5.group', 'creativity_learning')
        ->assertJsonPath('data.8.group', 'results')
        ->assertJsonPath('data.0.name.ar', 'القيادة الإدارية');

    expect(array_keys($response->json('data.0')))->toBe(['id', 'code', 'order', 'group', 'name', 'description'])
        ->and(collect($response->json('data'))->pluck('order')->all())->toBe(range(1, 9));
});

it('answers an empty model section before anything is published', function (): void {
    $this->getJson('/api/v1/content/model-criteria')->assertOk()->assertJsonPath('data', []);
});

it('rate limits the public model section', function (): void {
    installedSeedCriteria();

    foreach (range(1, 120) as $ignored) {
        $this->getJson('/api/v1/content/model-criteria')->assertOk();
    }

    $this->getJson('/api/v1/content/model-criteria')->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});

it('keeps the main criteria list of the published version stable for assessments', function (): void {
    $published = installedSeedCriteria();

    expect(MainCriterion::query()->where('criteria_version_id', $published->id)->count())->toBe(9);
});
