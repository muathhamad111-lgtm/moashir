<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Certificates\CertificateSupersession;
use App\Services\Certificates\CertificateSuspender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const ENTITIES_LIST = '/api/v1/entities';

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return array<string, string>
 */
function uniformMarks(string $percent): array
{
    return array_fill_keys(['M1.1', 'M1.2', 'M2.1', 'M2.2'], $percent);
}

function levelCriteria(): CriteriaVersion
{
    return CriteriaVersion::query()->where('status', CriteriaVersionStatus::Published)->first() ?? insightsCriteria();
}

function certifiedEntityAt(Entity $entity, string $percent): Certificate
{
    $version = levelCriteria();

    return certifyResult(accreditedResultFor($entity, $version, uniformMarks($percent)));
}

/**
 * @return array<int, array<string, mixed>>
 */
function listedEntities(string $query = ''): array
{
    return collect(switchTo(systemAdmin())->getJson(ENTITIES_LIST.$query)->assertOk()->json('data'))->keyBy('id')->all();
}

function entityListQueryCount(): int
{
    $admin = systemAdmin();
    switchTo($admin)->getJson(ENTITIES_LIST.'?sort=-level&per_page=50')->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    switchTo($admin)->getJson(ENTITIES_LIST.'?sort=-level&per_page=50')->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('shows the level and the certificate number of the latest issued accredited certificate on every row', function (): void {
    $gold = entityWithOwner();
    $certificate = certifiedEntityAt($gold, '85.00');
    $participation = entityWithOwner();
    certifiedEntityAt($participation, '50.00');
    $none = entityWithOwner();

    $rows = listedEntities();

    expect($rows[$gold->id]['level'])->toBe('gold')
        ->and($rows[$gold->id]['level_certificate_number'])->toBe($certificate->number)
        ->and($rows[$participation->id]['level'])->toBe('participation')
        ->and($rows[$participation->id]['level_certificate_number'])->not->toBeNull()
        ->and($rows[$none->id]['level'])->toBeNull()
        ->and($rows[$none->id]['level_certificate_number'])->toBeNull();
});

it('takes the newest issued certificate and falls back past a revoked one', function (): void {
    $entity = entityWithOwner();
    $version = levelCriteria();
    $older = certifyResult(accreditedResultFor($entity, $version, uniformMarks('65.00'), '2026-01-01 09:00:00'));
    certifyResult(accreditedResultFor($entity, $version, uniformMarks('95.00'), '2026-06-01 09:00:00'));

    expect(listedEntities()[$entity->id]['level'])->toBe('diamond');

    revokeResultCertificate(accreditedResultFor($entity, $version, uniformMarks('75.00'), '2026-09-01 09:00:00'));
    $newest = Certificate::query()->where('entity_id', $entity->id)->latest('id')->firstOrFail();

    expect($newest->status->value)->toBe('revoked')
        ->and(listedEntities()[$entity->id]['level'])->toBe('diamond');

    Certificate::query()->where('entity_id', $entity->id)->where('status', 'issued')->where('id', '!=', $older->id)->get()
        ->each(static fn (Certificate $certificate) => app(CertificateSupersession::class)->supersede($certificate));

    expect(listedEntities()[$entity->id])->toMatchArray(['level' => 'bronze', 'level_certificate_number' => $older->number]);
});

it('never counts a revoked, suspended or superseded certificate', function (string $outcome): void {
    $entity = entityWithOwner();
    $certificate = certifiedEntityAt($entity, '85.00');

    match ($outcome) {
        'revoked' => revokeResultCertificate($certificate->assessment),
        'suspended' => app(CertificateSuspender::class)->onReopen($certificate->assessment, CERTIFICATE_REASON),
        'superseded' => app(CertificateSupersession::class)->supersede($certificate),
    };

    expect($certificate->refresh()->status->value)->toBe($outcome)
        ->and(listedEntities()[$entity->id])->toMatchArray(['level' => null, 'level_certificate_number' => null]);

    switchTo(systemAdmin())->getJson(ENTITIES_LIST.'?filter[level]=none')->assertOk()->assertJsonPath('data.0.id', $entity->id);
})->with(['revoked', 'suspended', 'superseded']);

it('filters the list by level, participation or no level, and keeps the counters in line', function (string $filter, string $expected): void {
    $entities = [
        'gold' => entityWithOwner(),
        'participation' => entityWithOwner(),
        'none' => entityWithOwner(),
    ];
    certifiedEntityAt($entities['gold'], '85.00');
    certifiedEntityAt($entities['participation'], '50.00');

    switchTo(systemAdmin())->getJson(ENTITIES_LIST.'?filter[level]='.$filter)->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.stats.total', 1)
        ->assertJsonPath('data.0.id', $entities[$expected]->id);
})->with([
    'a metal level' => ['gold', 'gold'],
    'participation' => ['participation', 'participation'],
    'no level' => ['none', 'none'],
]);

it('returns an empty page for a level nobody holds', function (): void {
    certifiedEntityAt(entityWithOwner(), '85.00');

    switchTo(systemAdmin())->getJson(ENTITIES_LIST.'?filter[level]=diamond')->assertOk()->assertJsonPath('meta.pagination.total', 0);
});

it('sorts by level from no level to diamond and back', function (): void {
    $none = entityWithOwner();
    $participation = entityWithOwner();
    certifiedEntityAt($participation, '50.00');
    $silver = entityWithOwner();
    certifiedEntityAt($silver, '75.00');
    $diamond = entityWithOwner();
    certifiedEntityAt($diamond, '95.00');
    $bronze = entityWithOwner();
    certifiedEntityAt($bronze, '65.00');
    $ascending = [$none->id, $participation->id, $bronze->id, $silver->id, $diamond->id];

    $admin = systemAdmin();

    expect(collect(switchTo($admin)->getJson(ENTITIES_LIST.'?sort=level')->assertOk()->json('data'))->pluck('id')->all())->toBe($ascending)
        ->and(collect(switchTo($admin)->getJson(ENTITIES_LIST.'?sort=-level')->assertOk()->json('data'))->pluck('id')->all())->toBe(array_reverse($ascending));
});

it('rejects an unknown level filter or in-progress as a filter', function (string $query): void {
    switchTo(systemAdmin())->getJson(ENTITIES_LIST.'?'.$query)->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
})->with([
    'unknown level' => ['filter[level]=platinum'],
    'in progress is not a level' => ['filter[level]=in_progress'],
    'array value' => ['filter[level][]=gold'],
    'unknown sort' => ['sort=level_rank'],
]);

it('resolves the levels of a page with a constant number of queries', function (): void {
    certifiedEntityAt(entityWithOwner(), '85.00');
    entityWithOwner();
    $before = entityListQueryCount();

    foreach (['50.00', '65.00', '75.00', '95.00'] as $percent) {
        certifiedEntityAt(entityWithOwner(), $percent);
        entityWithOwner();
    }

    expect(entityListQueryCount())->toBe($before);
});

it('shows the level to the finance officer through the name and contact view', function (): void {
    $entity = entityWithOwner();
    certifiedEntityAt($entity, '85.00');

    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson(ENTITIES_LIST)->assertOk()
        ->assertJsonPath('data.0.level', 'gold')
        ->assertJsonMissingPath('data.0.has_open_accredited_request');
});

it('denies the level fields to roles without the entities permission', function (RoleKey $role): void {
    certifiedEntityAt(entityWithOwner(), '85.00');

    switchTo($role === RoleKey::Entity ? ownerOf(entityWithOwner()) : staffUser($role))
        ->getJson(ENTITIES_LIST.'?filter[level]=gold&sort=-level')->assertForbidden();
})->with([RoleKey::ContentOfficer, RoleKey::Entity]);

it('flags an open paid accredited request on the staff entity record', function (string $case, bool $expected): void {
    $entity = entityWithOwner();
    $version = levelCriteria();

    match ($case) {
        'submitted' => insightsAssessment($entity, $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted),
        'draft after payment' => openedAccreditedRequest($entity),
        'awaiting payment' => Assessment::factory()->forEntity($entity)->boundTo($version)->create([
            'request_number' => 'ACR-2026-000901',
            'path' => AssessmentPath::Accredited,
            'status' => AccreditedAssessmentStatus::AwaitingPayment,
            'payment_id' => Payment::factory()->forEntity($entity)->create()->id,
        ]),
        'completed' => accreditedResultFor($entity, $version, uniformMarks('85.00')),
        'nothing' => null,
    };

    switchTo(systemAdmin())->getJson(ENTITIES_LIST.'/'.$entity->id)->assertOk()
        ->assertJsonPath('data.has_open_accredited_request', $expected);

    expect(listedEntities()[$entity->id]['has_open_accredited_request'])->toBe($expected);
})->with([
    'submitted' => ['submitted', true],
    'paid draft' => ['draft after payment', true],
    'unpaid' => ['awaiting payment', false],
    'completed' => ['completed', false],
    'no request' => ['nothing', false],
]);

it('keeps the level beside the open re-assessment flag on the staff record', function (): void {
    $entity = entityWithOwner();
    $certificate = certifiedEntityAt($entity, '85.00');
    insightsAssessment($entity, levelCriteria(), AssessmentPath::Accredited, AccreditedAssessmentStatus::UnderReview);

    switchTo(systemAdmin())->getJson(ENTITIES_LIST.'/'.$entity->id)->assertOk()
        ->assertJsonPath('data.level', 'gold')
        ->assertJsonPath('data.level_certificate_number', $certificate->number)
        ->assertJsonPath('data.has_open_accredited_request', true);
});

it('adds the level to the entity of every request queue row with a constant number of queries', function (): void {
    $version = levelCriteria();
    $gold = entityWithOwner();
    certifyResult(accreditedResultFor($gold, $version, uniformMarks('85.00')));
    $goldRequest = insightsAssessment($gold, $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);
    $newcomer = insightsAssessment(entityWithOwner(), $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);
    $follower = requestFollower();

    $rows = collect(switchTo($follower)->getJson('/api/v1/requests')->assertOk()->json('data'))->keyBy('id');

    expect($rows[$goldRequest->id]['entity']['level'])->toBe('gold')
        ->and($rows[$newcomer->id]['entity']['level'])->toBeNull()
        ->and($rows[$newcomer->id]['entity'])->not->toHaveKey('level_certificate_number');

    switchTo($follower)->getJson('/api/v1/requests/'.$goldRequest->id)->assertOk()->assertJsonPath('data.entity.level', 'gold');

    $countQueries = static function () use ($follower): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        switchTo($follower)->getJson('/api/v1/requests?per_page=50')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $before = $countQueries();

    foreach (['50.00', '95.00'] as $percent) {
        $entity = entityWithOwner();
        certifyResult(accreditedResultFor($entity, $version, uniformMarks($percent)));
        insightsAssessment($entity, $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);
    }

    expect($countQueries())->toBe($before);
});

it('applies the level filter to the export and records it in the audit', function (): void {
    certifiedEntityAt(entityWithOwner(), '85.00');
    entityWithOwner();

    switchTo(systemAdmin())->postJson('/api/v1/entities/export', ['filter' => ['level' => 'gold']])->assertOk()
        ->assertHeader('X-Export-Row-Count', '1');

    expect(Audit::query()->where('event', AuditEvent::EntitiesExported->value)->firstOrFail()->new_values['filters']['level'] ?? null)->toBe('gold');
});
