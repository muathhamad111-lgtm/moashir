<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\EvidenceStatus;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Services\Certificates\CertificateSuspender;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
    Date::setTestNow('2026-11-05 08:00:00');
});

function homeCriteria(): CriteriaVersion
{
    return CriteriaVersion::query()->where('status', CriteriaVersionStatus::Published)->first() ?? insightsCriteria();
}

/**
 * @return array<string, string>
 */
function homeMarks(string $first, string $second = ''): array
{
    $second = $second === '' ? $first : $second;

    return ['M1.1' => $first, 'M1.2' => $first, 'M2.1' => $second, 'M2.2' => $second];
}

function levelledEntity(string $percent): Entity
{
    $entity = entityWithOwner();
    certifyResult(accreditedResultFor($entity, homeCriteria(), homeMarks($percent)));

    return $entity;
}

function openAccreditedRequestFor(Entity $entity): Assessment
{
    return insightsAssessment($entity, homeCriteria(), AssessmentPath::Accredited, AccreditedAssessmentStatus::UnderReview);
}

function evidenceOn(Assessment $assessment, MalwareScanStatus $scan, EvidenceStatus $status = EvidenceStatus::Uploaded): EvidenceFile
{
    return EvidenceFile::query()->forceCreate([
        'assessment_id' => $assessment->id,
        'sub_code' => 'M1.1',
        'stored_file_id' => storedTestFile([
            'purpose' => StoredFilePurpose::AssessmentEvidence,
            'malware_scan_status' => $scan,
            'malware_scanned_at' => $scan === MalwareScanStatus::Pending ? null : Date::now(),
        ])->id,
        'status' => $status,
        'removed_at' => $status === EvidenceStatus::Removed ? Date::now() : null,
    ]);
}

it('counts entities by level on the System Administrator and executive boards', function (string $board): void {
    entityWithOwner();
    openAccreditedRequestFor(entityWithOwner());
    levelledEntity('50.00');
    levelledEntity('65.00');
    openAccreditedRequestFor(levelledEntity('85.00'));
    levelledEntity('85.00');
    revokeResultCertificate(accreditedResultFor(entityWithOwner(), homeCriteria(), homeMarks('95.00')));
    $suspended = entityWithOwner();
    app(CertificateSuspender::class)->onReopen(accreditedResultFor($suspended, homeCriteria(), homeMarks('75.00')), CERTIFICATE_REASON);
    certifyResult(accreditedResultFor($suspended, homeCriteria(), homeMarks('75.00')));
    levelledEntity('95.00')->delete();

    expect(boardWidget(boardOf(systemAdmin(), $board), 'entities_by_level'))->toBe([
        ['key' => 'none', 'count' => 2],
        ['key' => 'in_progress', 'count' => 1],
        ['key' => 'participation', 'count' => 1],
        ['key' => 'bronze', 'count' => 1],
        ['key' => 'silver', 'count' => 1],
        ['key' => 'gold', 'count' => 2],
        ['key' => 'diamond', 'count' => 0],
    ]);
})->with(['system_admin', 'executive']);

it('answers every bucket with zero when there are no entities', function (): void {
    expect(collect(boardWidget(boardOf(systemAdmin(), 'system_admin'), 'entities_by_level'))->pluck('count', 'key')->all())->toBe([
        'none' => 0, 'in_progress' => 0, 'participation' => 0, 'bronze' => 0, 'silver' => 0, 'gold' => 0, 'diamond' => 0,
    ]);
});

it('keeps the boards with the level widget at a constant number of queries', function (): void {
    $admin = systemAdmin();
    levelledEntity('85.00');
    $before = [dashboardQueryCount($admin, 'system_admin'), dashboardQueryCount($admin, 'executive')];

    levelledEntity('50.00');
    levelledEntity('95.00');
    openAccreditedRequestFor(entityWithOwner());

    expect([dashboardQueryCount($admin, 'system_admin'), dashboardQueryCount($admin, 'executive')])->toBe($before);
});

it('does not show the level widget on boards without the global entity view', function (RoleKey $role): void {
    $user = $role === RoleKey::Entity ? ownerOf(entityWithOwner()) : staffUser($role);

    switchTo($user)->getJson(DASHBOARD.'?board=system_admin')->assertForbidden()->assertJsonPath('code', 'board.not_held');
    switchTo($user)->getJson(DASHBOARD.'?board=executive')->assertForbidden();
    expect(collect(boardOf($user)->json('data.widgets'))->pluck('key'))->not->toContain('entities_by_level');
})->with([RoleKey::FinanceOfficer, RoleKey::RequestFollower, RoleKey::Entity]);

it('counts the accepted evidence of the entity on its board', function (): void {
    $entity = entityWithOwner();
    $completed = accreditedResultFor($entity, homeCriteria(), homeMarks('85.00'));
    $request = openAccreditedRequestFor($entity);
    evidenceOn($request, MalwareScanStatus::Clean);
    evidenceOn($completed, MalwareScanStatus::Clean);
    evidenceOn($request, MalwareScanStatus::Pending);
    evidenceOn($request, MalwareScanStatus::Infected);
    evidenceOn($request, MalwareScanStatus::Clean, EvidenceStatus::Removed);
    evidenceOn(openAccreditedRequestFor(entityWithOwner()), MalwareScanStatus::Clean);

    $card = collect(boardOf(ownerOf($entity), 'entity')->json('data.cards'))->firstWhere('key', 'accepted_evidence_count');

    expect($card)->toBe(['key' => 'accepted_evidence_count', 'value' => 2, 'format' => 'count', 'link' => null]);
});

it('reports zero accepted evidence for an entity without uploads', function (): void {
    expect(boardCards(boardOf(ownerOf(entityWithOwner()), 'entity'))->get('accepted_evidence_count'))->toBe(0);
});

it('reads the main criteria widget from the latest eligible accredited result only, never self', function (): void {
    $entity = entityWithOwner();
    $version = homeCriteria();
    accreditedResultFor($entity, $version, homeMarks('70.00', '60.00'), '2026-03-01 09:00:00');
    selfResultFor($entity, $version);

    $response = boardOf(ownerOf($entity), 'entity');

    expect(boardWidget($response, 'latest_result')['path'])->toBe('self')
        ->and(collect(boardWidget($response, 'criteria_progress'))->pluck('percent', 'code')->all())->toBe(['M1' => '70.00', 'M2' => '60.00']);
});

it('skips a revoked accredited result in the main criteria widget', function (): void {
    $entity = entityWithOwner();
    $version = homeCriteria();
    accreditedResultFor($entity, $version, homeMarks('70.00', '60.00'), '2026-03-01 09:00:00');
    revokeResultCertificate(accreditedResultFor($entity, $version, homeMarks('90.00'), '2026-06-01 09:00:00'));

    expect(collect(boardWidget(boardOf(ownerOf($entity), 'entity'), 'criteria_progress'))->pluck('percent', 'code')->all())->toBe(['M1' => '70.00', 'M2' => '60.00']);
});

it('leaves the main criteria widget empty when the entity has only self results', function (): void {
    $entity = entityWithOwner();
    selfResultFor($entity, homeCriteria());

    $response = boardOf(ownerOf($entity), 'entity');

    expect(boardWidget($response, 'criteria_progress'))->toBe([])
        ->and(boardWidget($response, 'latest_result')['path'])->toBe('self');
});

it('stamps the action center with the time it was generated', function (): void {
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/dashboard/action-center')->assertOk()
        ->assertJsonPath('meta.generated_at', '2026-11-05T08:00:00+00:00')
        ->assertJsonStructure(['meta' => ['total', 'generated_at']]);
});

it('keeps the action center closed to guests', function (): void {
    $this->getJson('/api/v1/dashboard/action-center')->assertUnauthorized();
});
