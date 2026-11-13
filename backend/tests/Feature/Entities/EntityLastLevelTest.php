<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Entities\EntityAccess;
use App\Enums\Identity\RoleKey;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Services\Certificates\CertificateSupersession;
use App\Services\Certificates\CertificateSuspender;
use App\Support\Entities\EntityResourceVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const LAST_LEVEL_ISSUED_AT = '2026-06-10 10:00';

const LAST_LEVEL_VIEW_DAY = '2026-09-15 10:00';

const LAST_LEVEL_EXPIRED_ROLLOUT = '2026-04-01';

const LAST_LEVEL_EXPIRING_ROLLOUT = '2026-07-01';

const LAST_LEVEL_VALID_ROLLOUT = '2026-10-01';

const LAST_LEVEL_SILVER = '75.00';

const LAST_LEVEL_BRONZE = '65.00';

const LAST_LEVEL_GOLD = '85.00';

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
    atRiyadh(LAST_LEVEL_ISSUED_AT);
});

function lastLevelCriteria(): CriteriaVersion
{
    return CriteriaVersion::query()->where('status', CriteriaVersionStatus::Published)->first() ?? insightsCriteria();
}

function lastLevelResult(Entity $entity, string $percent): Assessment
{
    return accreditedResultFor($entity, lastLevelCriteria(), array_fill_keys(['M1.1', 'M1.2', 'M2.1', 'M2.2'], $percent));
}

function certifiedUntilEndOfQuarter(string $rollout, string $percent, ?Entity $entity = null): Entity
{
    setPeriodicSetting(SettingKey::PeriodicCadence, PeriodCadence::Quarterly->value);
    setPeriodicSetting(SettingKey::PeriodicRolloutFrom, $rollout);
    $entity ??= entityWithOwner();
    certifyResult(lastLevelResult($entity, $percent));

    return $entity;
}

function expiredSilverEntity(): Entity
{
    return certifiedUntilEndOfQuarter(LAST_LEVEL_EXPIRED_ROLLOUT, LAST_LEVEL_SILVER);
}

function expiringBronzeEntity(): Entity
{
    return certifiedUntilEndOfQuarter(LAST_LEVEL_EXPIRING_ROLLOUT, LAST_LEVEL_BRONZE);
}

function validGoldEntity(): Entity
{
    return certifiedUntilEndOfQuarter(LAST_LEVEL_VALID_ROLLOUT, LAST_LEVEL_GOLD);
}

/**
 * @return array<int, array<string, mixed>>
 */
function lastLevelRows(string $query = ''): array
{
    return collect(switchTo(systemAdmin())->getJson('/api/v1/entities'.$query)->assertOk()->json('data'))->keyBy('id')->all();
}

/**
 * @return list<int>
 */
function lastLevelIds(string $query): array
{
    return collect(switchTo(systemAdmin())->getJson('/api/v1/entities'.$query)->assertOk()->json('data'))->pluck('id')->all();
}

/**
 * @return array<string, mixed>
 */
function expiredSilverLastLevel(): array
{
    return ['level' => 'silver', 'valid_until' => '2026-06-30', 'validity' => 'expired', 'days_until_expiry' => null];
}

function lastLevelQueryCount(string $url): int
{
    $admin = systemAdmin();
    switchTo($admin)->getJson($url)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    switchTo($admin)->getJson($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('shows the last level with its validity beside the counted level on every entity row', function (): void {
    $expired = expiredSilverEntity();
    $expiring = expiringBronzeEntity();
    $valid = validGoldEntity();
    $never = entityWithOwner();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    $rows = lastLevelRows();

    expect($rows[$expired->id])->toMatchArray(['level' => null, 'level_certificate_number' => null, 'level_valid_until' => null, 'last_level' => expiredSilverLastLevel()])
        ->and($rows[$expiring->id])->toMatchArray([
            'level' => 'bronze',
            'level_valid_until' => '2026-09-30',
            'last_level' => ['level' => 'bronze', 'valid_until' => '2026-09-30', 'validity' => 'expiring_soon', 'days_until_expiry' => 15],
        ])
        ->and($rows[$valid->id])->toMatchArray([
            'level' => 'gold',
            'last_level' => ['level' => 'gold', 'valid_until' => '2026-12-31', 'validity' => 'valid', 'days_until_expiry' => 107],
        ])
        ->and($rows[$never->id])->toMatchArray(['level' => null, 'last_level' => null]);
});

it('gives the last level to the finance officer through the name and contact view', function (): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson('/api/v1/entities')->assertOk()
        ->assertJsonPath('data.0.id', $expired->id)
        ->assertJsonPath('data.0.level', null)
        ->assertJsonPath('data.0.last_level', expiredSilverLastLevel())
        ->assertJsonMissingPath('data.0.has_open_accredited_request');
});

it('gives the last level to the name-only variant of the entity', function (): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    $resolved = EntityResourceVariant::of(Entity::query()->findOrFail($expired->id), EntityAccess::None)->resolve(request());

    expect($resolved)->toMatchArray(['level' => null, 'last_level' => expiredSilverLastLevel()]);
});

it('returns the last level from every staff entity record endpoint', function (string $method, string $suffix, bool $withProfile): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);
    $payload = $withProfile ? [...profilePayload($expired), 'reason' => 'تحديث البيانات الأساسية للجهة.'] : ['reason' => 'سبب موثق للإجراء الإداري على الجهة.'];

    switchTo(systemAdmin())->json($method, '/api/v1/entities/'.$expired->id.$suffix, $method === 'GET' ? [] : $payload)->assertOk()
        ->assertJsonPath('data.level', null)
        ->assertJsonPath('data.last_level', expiredSilverLastLevel());
})->with([
    'show' => ['GET', '', false],
    'update' => ['PUT', '', true],
    'deactivate' => ['POST', '/deactivate', false],
]);

it('returns the last level after the entity is reactivated or restored', function (): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);
    $admin = systemAdmin();
    $reason = ['reason' => 'سبب موثق للإجراء الإداري على الجهة.'];

    switchTo($admin)->postJson("/api/v1/entities/{$expired->id}/deactivate", $reason)->assertOk();
    switchTo($admin)->postJson("/api/v1/entities/{$expired->id}/reactivate", $reason)->assertOk()
        ->assertJsonPath('data.last_level', expiredSilverLastLevel());
    switchTo($admin)->deleteJson("/api/v1/entities/{$expired->id}", $reason)->assertNoContent();
    switchTo($admin)->postJson("/api/v1/entities/{$expired->id}/restore", $reason)->assertOk()
        ->assertJsonPath('data.last_level', expiredSilverLastLevel());
});

it('adds the last level to the entity of a request row and of the request detail', function (): void {
    $expired = expiredSilverEntity();
    $request = insightsAssessment($expired, lastLevelCriteria(), AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);
    atRiyadh(LAST_LEVEL_VIEW_DAY);
    $follower = requestFollower();

    $row = collect(switchTo($follower)->getJson('/api/v1/requests')->assertOk()->json('data'))->firstWhere('id', $request->id);

    expect($row['entity'])->toMatchArray(['level' => null, 'last_level' => expiredSilverLastLevel()])
        ->and($row['entity'])->not->toHaveKey('level_certificate_number');

    switchTo($follower)->getJson("/api/v1/requests/{$request->id}")->assertOk()
        ->assertJsonPath('data.entity.level', null)
        ->assertJsonPath('data.entity.last_level', expiredSilverLastLevel());
});

it('lists the expired certificate first with its validity for the record level card', function (): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    switchTo(systemAdmin())->getJson('/api/v1/certificates?filter[entity_id]='.$expired->id)->assertOk()
        ->assertJsonPath('data.0.level', 'silver')
        ->assertJsonPath('data.0.status', 'issued')
        ->assertJsonPath('data.0.validity', 'expired')
        ->assertJsonPath('data.0.valid_until', '2026-06-30');
});

it('falls back past a revoked newer certificate to the older expired one', function (): void {
    $entity = expiredSilverEntity();
    revokeResultCertificate(lastLevelResult($entity, '95.00'));
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    expect(lastLevelRows()[$entity->id]['last_level'])->toBe(expiredSilverLastLevel());
});

it('never takes a revoked, suspended or superseded certificate as the last level', function (string $outcome): void {
    $entity = expiredSilverEntity();
    $certificate = Certificate::query()->where('entity_id', $entity->id)->sole();

    match ($outcome) {
        'revoked' => revokeResultCertificate($certificate->assessment),
        'suspended' => app(CertificateSuspender::class)->onReopen($certificate->assessment, CERTIFICATE_REASON),
        'superseded' => app(CertificateSupersession::class)->supersede($certificate),
    };
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    expect($certificate->refresh()->status->value)->toBe($outcome)
        ->and(lastLevelRows()[$entity->id]['last_level'])->toBeNull()
        ->and(lastLevelIds('?filter[level]=expired'))->toBe([]);
})->with(['revoked', 'suspended', 'superseded']);

it('breaks an issue-time tie between two certificates by the newest one', function (): void {
    $entity = expiredSilverEntity();
    certifyResult(lastLevelResult($entity, LAST_LEVEL_GOLD));
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    expect(Certificate::query()->where('entity_id', $entity->id)->distinct()->count('issued_at'))->toBe(1)
        ->and(lastLevelRows()[$entity->id]['last_level']['level'])->toBe('gold');
});

it('counts the certificate on its last valid day and shows it expired the next morning', function (): void {
    $entity = expiringBronzeEntity();

    atRiyadh('2026-09-30 23:59');
    expect(lastLevelRows()[$entity->id])->toMatchArray([
        'level' => 'bronze',
        'last_level' => ['level' => 'bronze', 'valid_until' => '2026-09-30', 'validity' => 'expiring_soon', 'days_until_expiry' => 0],
    ]);

    atRiyadh('2026-10-01 00:01');
    expect(lastLevelRows()[$entity->id])->toMatchArray([
        'level' => null,
        'last_level' => ['level' => 'bronze', 'valid_until' => '2026-09-30', 'validity' => 'expired', 'days_until_expiry' => null],
    ]);
});

it('resolves the last levels of an entity page and a request page with a constant number of queries', function (): void {
    $request = static fn (Entity $entity): Assessment => insightsAssessment($entity, lastLevelCriteria(), AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);
    $request(expiredSilverEntity());
    atRiyadh(LAST_LEVEL_VIEW_DAY);
    $before = [lastLevelQueryCount('/api/v1/entities?per_page=50'), lastLevelQueryCount('/api/v1/requests?per_page=50')];

    atRiyadh(LAST_LEVEL_ISSUED_AT);
    foreach ([expiredSilverEntity(), expiringBronzeEntity(), validGoldEntity(), entityWithOwner()] as $entity) {
        $request($entity);
    }
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    expect([lastLevelQueryCount('/api/v1/entities?per_page=50'), lastLevelQueryCount('/api/v1/requests?per_page=50')])->toBe($before);
});

it('does not count an expired entity at its old level and keeps it under no level', function (): void {
    $expired = expiredSilverEntity();
    $validSilver = certifiedUntilEndOfQuarter(LAST_LEVEL_VALID_ROLLOUT, LAST_LEVEL_SILVER);
    $never = entityWithOwner();
    atRiyadh(LAST_LEVEL_VIEW_DAY);
    $admin = systemAdmin();

    switchTo($admin)->getJson('/api/v1/entities?filter[level]=silver')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.stats.total', 1)
        ->assertJsonPath('data.0.id', $validSilver->id);

    expect(lastLevelIds('?filter[level]=none'))->toEqualCanonicalizing([$expired->id, $never->id]);
    switchTo($admin)->getJson('/api/v1/entities?filter[level]=none')->assertOk()->assertJsonPath('meta.stats.total', 2);
});

it('filters the entities whose last level has expired, as a subset of no level', function (): void {
    $expired = expiredSilverEntity();
    expiringBronzeEntity();
    validGoldEntity();
    entityWithOwner();
    revokeResultCertificate(lastLevelResult(entityWithOwner(), LAST_LEVEL_GOLD));
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    switchTo(systemAdmin())->getJson('/api/v1/entities?filter[level]=expired')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.stats.total', 1)
        ->assertJsonPath('data.0.id', $expired->id)
        ->assertJsonPath('data.0.last_level.validity', 'expired');

    expect(lastLevelIds('?filter[level]=none'))->toContain($expired->id);
});

it('keeps an entity out of the expired filter while an older certificate is still valid', function (): void {
    $entity = certifiedUntilEndOfQuarter(LAST_LEVEL_VALID_ROLLOUT, LAST_LEVEL_SILVER);
    atRiyadh('2026-06-11 10:00');
    certifiedUntilEndOfQuarter(LAST_LEVEL_EXPIRED_ROLLOUT, LAST_LEVEL_GOLD, $entity);
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    expect(lastLevelRows()[$entity->id])->toMatchArray([
        'level' => 'silver',
        'last_level' => ['level' => 'gold', 'valid_until' => '2026-06-30', 'validity' => 'expired', 'days_until_expiry' => null],
    ])
        ->and(lastLevelIds('?filter[level]=expired'))->toBe([])
        ->and(lastLevelIds('?filter[level]=silver'))->toBe([$entity->id]);
});

it('sorts an expired entity with the entities that have no level', function (): void {
    $never = entityWithOwner();
    $expired = expiredSilverEntity();
    $bronze = expiringBronzeEntity();
    $gold = validGoldEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    $ascending = lastLevelIds('?sort=level');
    $descending = lastLevelIds('?sort=-level');

    expect(array_slice($ascending, 2))->toBe([$bronze->id, $gold->id])
        ->and(array_slice($ascending, 0, 2))->toEqualCanonicalizing([$never->id, $expired->id])
        ->and(array_slice($descending, 0, 2))->toBe([$gold->id, $bronze->id])
        ->and(array_slice($descending, 2))->toEqualCanonicalizing([$never->id, $expired->id]);
});

it('exports the counted level only and records the expired filter in the audit', function (string $filter, int $rows): void {
    expiredSilverEntity();
    certifiedUntilEndOfQuarter(LAST_LEVEL_VALID_ROLLOUT, LAST_LEVEL_SILVER);
    entityWithOwner();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    switchTo(systemAdmin())->postJson('/api/v1/entities/export', ['filter' => ['level' => $filter]])->assertOk()
        ->assertHeader('X-Export-Row-Count', (string) $rows);

    expect(Audit::query()->where('event', AuditEvent::EntitiesExported->value)->latest('id')->firstOrFail()->new_values['filters']['level'] ?? null)->toBe($filter);
})->with([
    'silver keeps only the valid silver entity' => ['silver', 1],
    'expired selects only the expired entity' => ['expired', 1],
    'no level keeps the expired and the never certified entities' => ['none', 2],
]);

it('counts an expired entity under no level or in progress on the level charts, never at its old level', function (string $board): void {
    expiredSilverEntity();
    $reassessed = expiredSilverEntity();
    insightsAssessment($reassessed, lastLevelCriteria(), AssessmentPath::Accredited, AccreditedAssessmentStatus::UnderReview);
    validGoldEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    expect(collect(boardWidget(boardOf(systemAdmin(), $board), 'entities_by_level'))->pluck('count', 'key')->all())->toBe([
        'none' => 1, 'in_progress' => 1, 'participation' => 0, 'bronze' => 0, 'silver' => 0, 'gold' => 1, 'diamond' => 0,
    ]);
})->with(['system_admin', 'executive']);

it('keeps the entities list and record closed to roles without the entities permission', function (RoleKey $role): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);
    $user = $role === RoleKey::Entity ? ownerOf(entityWithOwner()) : staffUser($role);

    switchTo($user)->getJson('/api/v1/entities?filter[level]=expired')->assertForbidden();
    switchTo($user)->getJson("/api/v1/entities/{$expired->id}")->assertForbidden();
})->with([RoleKey::ContentOfficer, RoleKey::CriteriaOfficer, RoleKey::Entity]);

it('hides an entity out of scope or deleted as not found', function (): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    switchTo(staffUser(RoleKey::Assessor))->getJson('/api/v1/entities?filter[level]=expired')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    switchTo(staffUser(RoleKey::Assessor))->getJson("/api/v1/entities/{$expired->id}")->assertNotFound();

    $expired->delete();
    switchTo(requestFollower())->getJson("/api/v1/entities/{$expired->id}")->assertNotFound();
});

it('keeps the request queue and detail closed as today', function (): void {
    $expired = expiredSilverEntity();
    $request = insightsAssessment($expired, lastLevelCriteria(), AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);
    $selfAssessment = insightsAssessment($expired, lastLevelCriteria(), AssessmentPath::SelfAssessment, SelfAssessmentStatus::Submitted);
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    switchTo(staffUser(RoleKey::ContentOfficer))->getJson('/api/v1/requests')->assertForbidden();
    switchTo(ownerOf($expired))->getJson('/api/v1/requests')->assertForbidden();
    switchTo(ownerOf($expired))->getJson("/api/v1/requests/{$request->id}")->assertForbidden();
    switchTo(requestFollower())->getJson("/api/v1/requests/{$selfAssessment->id}")->assertNotFound();
});

it('keeps the certificates of an entity closed to staff without the certificates permission', function (): void {
    $expired = expiredSilverEntity();
    atRiyadh(LAST_LEVEL_VIEW_DAY);

    switchTo(requestFollower())->getJson('/api/v1/certificates?filter[entity_id]='.$expired->id)->assertForbidden();
    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson('/api/v1/certificates?filter[entity_id]='.$expired->id)->assertForbidden();
});

it('answers guests with unauthenticated on every level endpoint', function (string $url): void {
    $this->getJson($url)->assertUnauthorized();
})->with([
    'entities' => ['/api/v1/entities?filter[level]=expired'],
    'entity' => ['/api/v1/entities/1'],
    'requests' => ['/api/v1/requests'],
    'request' => ['/api/v1/requests/1'],
    'certificates' => ['/api/v1/certificates?filter[entity_id]=1'],
    'dashboard' => ['/api/v1/dashboard/summary?board=system_admin'],
]);
