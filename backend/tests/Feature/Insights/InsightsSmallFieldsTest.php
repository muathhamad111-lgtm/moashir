<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

const SMALL_FIELDS_MARKS = ['M1.1' => '85.00', 'M1.2' => '45.00', 'M2.1' => '75.00', 'M2.2' => '90.00'];

beforeEach(function (): void {
    Notification::fake();
});

function executiveSummaryUrl(Assessment $assessment): string
{
    return "/api/v1/assessments/{$assessment->id}/insights/executive-summary";
}

function withCleanLogo(Entity $entity): StoredFile
{
    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertCreated();

    return StoredFile::query()->findOrFail(Entity::query()->findOrFail($entity->id)->logo_file_id);
}

/**
 * @return array<string, mixed>
 */
function feedbackLibraryItem(string $url): array
{
    return (array) test()->getJson($url)->assertOk()->json('data.0.items.0');
}

it('gives the executive summary a short-lived signed logo link that the viewer can download', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    $logo = withCleanLogo($assessment->entity);
    $owner = ownerOf($assessment->entity);

    $logoUrl = (string) switchTo($owner)->getJson(executiveSummaryUrl($assessment))->assertOk()
        ->assertJsonPath('data.entity.has_logo', true)
        ->json('data.entity.logo_url');

    expect($logoUrl)->toContain("/api/v1/files/{$logo->public_id}/download")
        ->toContain('signature=')
        ->toContain('expires=')
        ->toContain("user={$owner->id}")
        ->not->toContain('storage')
        ->not->toContain((string) $logo->path);
    $this->get($logoUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
    switchTo(systemAdmin())->get($logoUrl)->assertForbidden()->assertJsonPath('code', 'signature.invalid');
});

it('binds the logo link to each staff viewer who may read the entity', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    withCleanLogo($assessment->entity);
    $admin = systemAdmin();

    $logoUrl = (string) switchTo($admin)->getJson(executiveSummaryUrl($assessment))->assertOk()->json('data.entity.logo_url');

    expect($logoUrl)->toContain("user={$admin->id}");
    $this->get($logoUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
    switchTo(superAdmin())->getJson(executiveSummaryUrl($assessment))->assertOk()->assertJsonPath('data.entity.has_logo', true);
    $followerLogoUrl = (string) switchTo(requestFollower())->getJson(executiveSummaryUrl($assessment))->assertOk()->json('data.entity.logo_url');
    $this->get($followerLogoUrl)->assertOk();
});

it('answers a null logo link when the entity has no logo or the logo is not scanned clean', function (): void {
    $version = insightsCriteria();
    $plain = accreditedResultFor(entityWithOwner(), $version, SMALL_FIELDS_MARKS);
    $pending = accreditedResultFor(entityWithOwner(), $version, SMALL_FIELDS_MARKS);
    $logo = withCleanLogo($pending->entity);
    DB::table('stored_files')->where('id', $logo->id)->update(['malware_scan_status' => MalwareScanStatus::Pending->value]);

    switchTo(ownerOf($plain->entity))->getJson(executiveSummaryUrl($plain))->assertOk()
        ->assertJsonPath('data.entity.has_logo', false)
        ->assertJsonPath('data.entity.logo_url', null);
    switchTo(ownerOf($pending->entity))->getJson(executiveSummaryUrl($pending))->assertOk()
        ->assertJsonPath('data.entity.has_logo', true)
        ->assertJsonPath('data.entity.logo_url', null);
});

it('gives no logo link to a viewer of the summary who may not view the entity', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    withCleanLogo($assessment->entity);
    Role::findByName(RoleKey::RequestFollower->value, 'web')->revokePermissionTo(PermissionKey::EntitiesView->value);

    switchTo(requestFollower())->getJson(executiveSummaryUrl($assessment))->assertOk()
        ->assertJsonPath('data.entity.has_logo', true)
        ->assertJsonPath('data.entity.logo_url', null);
});

it('keeps the executive summary and its logo link from other entities and assessors', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    withCleanLogo($assessment->entity);

    switchTo(ownerOf(entityWithOwner()))->getJson(executiveSummaryUrl($assessment))->assertNotFound();
    switchTo(assessorUser())->getJson(executiveSummaryUrl($assessment))->assertForbidden();
    switchTo(followerOfPaths(AssessmentPath::Advanced))->getJson(executiveSummaryUrl($assessment))->assertNotFound();
});

it('prints the certificate number on every result reference and keeps the certificate id', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $assessment = accreditedResultFor($entity, $version, SMALL_FIELDS_MARKS);
    $certificate = certifyResult($assessment);
    $owner = ownerOf($entity);

    expect($certificate->number)->toStartWith('CRT-');
    switchTo($owner)->getJson('/api/v1/entities/me/insights')->assertOk()
        ->assertJsonPath('data.selected.certificate.number', $certificate->number)
        ->assertJsonPath('data.selected.certificate.id', $certificate->public_id);
    switchTo($owner)->getJson('/api/v1/entities/me/insights/results')->assertOk()->assertJsonPath('data.0.certificate.number', $certificate->number);
    switchTo($owner)->getJson('/api/v1/entities/me/report-library')->assertOk()->assertJsonPath('data.0.result.certificate.number', $certificate->number);
    switchTo($owner)->getJson(executiveSummaryUrl($assessment))->assertOk()->assertJsonPath('data.result.certificate.number', $certificate->number);
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/insights/maturity")->assertOk()->assertJsonPath('data.result.certificate.number', $certificate->number);
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/insights/strengths-gaps")->assertOk()->assertJsonPath('data.result.certificate.number', $certificate->number);
    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}/insights")->assertOk()->assertJsonPath('data.selected.certificate.number', $certificate->number);
});

it('keeps the number of a revoked certificate beside its status and has no certificate without one', function (): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $plain = accreditedResultFor($entity, $version, SMALL_FIELDS_MARKS, '2026-01-01 09:00:00');
    $revoked = accreditedResultFor($entity, $version, SMALL_FIELDS_MARKS, '2026-06-01 09:00:00');
    $certificate = revokeResultCertificate($revoked);

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me/report-library')->assertOk()
        ->assertJsonPath('data.0.result.assessment_id', $revoked->id)
        ->assertJsonPath('data.0.result.certificate.status', 'revoked')
        ->assertJsonPath('data.0.result.certificate.number', $certificate->number)
        ->assertJsonPath('data.1.result.assessment_id', $plain->id)
        ->assertJsonPath('data.1.result.certificate', null);
});

it('offers the entity a signed feedback report PDF link bound to the viewer on the library row', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    $owner = ownerOf($assessment->entity);

    switchTo($owner);
    $item = feedbackLibraryItem('/api/v1/entities/me/report-library');

    expect($item['kind'])->toBe('feedback_report')
        ->and($item['available'])->toBeTrue()
        ->and($item['pdf_available'])->toBeTrue()
        ->and((string) $item['pdf_url'])->toContain("/api/v1/assessments/{$assessment->id}/feedback-report/pdf")
        ->toContain('locale=ar')
        ->toContain("user={$owner->id}")
        ->toContain('signature=');
    $this->get((string) $item['pdf_url'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    switchTo(systemAdmin())->get((string) $item['pdf_url'])->assertForbidden()->assertJsonPath('code', 'signature.invalid');
});

it('uses the request language for the library PDF link', function (): void {
    accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    $owner = ownerOf(Assessment::query()->sole()->entity);

    $url = (string) switchTo($owner)->getJson('/api/v1/entities/me/report-library', ['Accept-Language' => 'en'])->assertOk()->json('data.0.items.0.pdf_url');

    expect($url)->toContain('locale=en');
});

it('binds the library PDF link to the staff viewer', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    $admin = systemAdmin();

    switchTo($admin);
    $item = feedbackLibraryItem("/api/v1/entities/{$assessment->entity_id}/report-library");

    expect($item['pdf_available'])->toBeTrue()->and((string) $item['pdf_url'])->toContain("user={$admin->id}");
    $this->get((string) $item['pdf_url'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    switchTo(requestFollower());
    $this->get((string) feedbackLibraryItem("/api/v1/entities/{$assessment->entity_id}/report-library")['pdf_url'])->assertOk();
});

it('has no PDF link while the feedback report is not available', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    certifyResult($assessment);
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();

    switchTo(ownerOf($assessment->entity))->getJson('/api/v1/entities/me/report-library')->assertOk()
        ->assertJsonPath('data.0.items.0.available', false)
        ->assertJsonPath('data.0.items.0.pdf_available', false)
        ->assertJsonPath('data.0.items.0.pdf_url', null);
});

it('has no PDF link for a library viewer without the feedback report permission', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    Role::findByName(RoleKey::RequestFollower->value, 'web')->revokePermissionTo(PermissionKey::ReportsFeedbackView->value);

    switchTo(requestFollower())->getJson("/api/v1/entities/{$assessment->entity_id}/report-library")->assertOk()
        ->assertJsonPath('data.0.items.0.available', true)
        ->assertJsonPath('data.0.items.0.pdf_available', false)
        ->assertJsonPath('data.0.items.0.pdf_url', null);
});

it('keeps the board report and certificate items without PDF link keys', function (): void {
    accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    $owner = ownerOf(Assessment::query()->sole()->entity);

    $items = (array) switchTo($owner)->getJson('/api/v1/entities/me/report-library')->assertOk()->json('data.0.items');

    expect($items[1])->not->toHaveKey('pdf_url')->and($items[2])->not->toHaveKey('pdf_url');
});

it('keeps the library from other entities and assessors', function (): void {
    $assessment = accreditedResultFor(entityWithOwner(), insightsCriteria(), SMALL_FIELDS_MARKS);
    $url = "/api/v1/entities/{$assessment->entity_id}/report-library";

    switchTo(ownerOf(entityWithOwner()))->getJson($url)->assertForbidden();
    switchTo(assessorUser())->getJson($url)->assertForbidden();
    switchTo(staffUser(RoleKey::ContentOfficer))->getJson($url)->assertForbidden();
    switchTo(ownerOf(entityWithOwner()))->getJson('/api/v1/entities/me/report-library')->assertOk()->assertJsonCount(0, 'data');
    switchTo(systemAdmin())->getJson('/api/v1/entities/999999/report-library')->assertNotFound();
});

it('reads the library links and certificate numbers with a constant number of queries', function (string $viewer): void {
    $version = insightsCriteria();
    $entity = entityWithOwner();
    $user = $viewer === 'owner' ? ownerOf($entity) : systemAdmin();
    $url = $viewer === 'owner' ? '/api/v1/entities/me/report-library' : "/api/v1/entities/{$entity->id}/report-library";
    $countQueries = static function () use ($user, $url): int {
        switchTo($user)->getJson($url)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        switchTo($user)->getJson($url)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    certifyResult(accreditedResultFor($entity, $version, SMALL_FIELDS_MARKS, '2026-01-01 09:00:00'));
    $one = $countQueries();
    certifyResult(accreditedResultFor($entity, $version, SMALL_FIELDS_MARKS, '2026-03-01 09:00:00'));
    certifyResult(accreditedResultFor($entity, $version, SMALL_FIELDS_MARKS, '2026-06-01 09:00:00'));

    expect($countQueries())->toBe($one);
})->with(['owner', 'staff']);
