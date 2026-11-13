<?php

declare(strict_types=1);

use App\Contracts\Documents\PdfRenderer;
use App\Data\Documents\PdfDocument;
use App\Integrations\Documents\UnavailablePdfRenderer;
use App\Integrations\Fakes\FakePdfRenderer;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Documents\DocumentHtmlComposer;
use App\Services\Entities\EntityEraser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
});

function logoDataUri(int $fileId, string $mimeType = 'image/png'): string
{
    return 'data:'.$mimeType.';base64,'.base64_encode(storedFileBytes($fileId));
}

function lastRenderedCertificate(): PdfDocument
{
    $documents = array_values(array_filter(app(FakePdfRenderer::class)->rendered(), static fn (PdfDocument $document): bool => $document->template === 'accredited_certificate'));

    return $documents[array_key_last($documents) ?? throw new LogicException('No certificate was rendered.')];
}

/**
 * @return array{assessment: Assessment, assessor: User, owner: User, logo_id: int}
 */
function scoredRequestWithLogo(): array
{
    $assessor = assessorUser();
    $assessment = scoredAccreditedReview(publishedAccreditedCriteria(), $assessor);
    $owner = ownerOf($assessment->entity);
    uploadEntityLogo($owner, logoUpload(logoImageBytes()))->assertCreated();

    return ['assessment' => $assessment, 'assessor' => $assessor, 'owner' => $owner, 'logo_id' => (int) Entity::query()->findOrFail($assessment->entity_id)->logo_file_id];
}

function completeReview(Assessment $assessment, User $assessor): Certificate
{
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk();

    return Certificate::query()->where('assessment_id', $assessment->id)->latest('id')->firstOrFail();
}

it('prints the entity logo beside the entity name of a certificate issued after the upload', function (): void {
    ['assessment' => $assessment, 'assessor' => $assessor, 'logo_id' => $logoId] = scoredRequestWithLogo();

    $certificate = completeReview($assessment, $assessor);
    $document = lastRenderedCertificate();
    $html = app(DocumentHtmlComposer::class)->compose($document);

    expect($certificate->entity_logo_file_id)->toBe($logoId)
        ->and($document->data['entity_logo'])->toBe(logoDataUri($logoId))
        ->and($html)->toContain('<div class="certificate__entity-row">')
        ->and($html)->toContain('<img class="certificate__entity-logo" src="'.logoDataUri($logoId).'"')
        ->and($html)->toContain('<img class="certificate__logo" src="data:image/svg+xml;base64,');
});

it('keeps the certificate layout unchanged when the entity has no logo', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();
    $document = lastRenderedCertificate();
    $html = app(DocumentHtmlComposer::class)->compose($document);

    expect($certificate->entity_logo_file_id)->toBeNull()
        ->and($document->data['entity_logo'])->toBeNull()
        ->and($html)->not->toContain('<img class="certificate__entity-logo"')
        ->and($html)->not->toContain('<div class="certificate__entity-row">')
        ->and($html)->toContain('<p class="certificate__entity">');
});

it('never changes a stored certificate PDF when the logo changes later', function (): void {
    ['assessment' => $assessment, 'assessor' => $assessor, 'owner' => $owner, 'logo_id' => $logoId] = scoredRequestWithLogo();
    $certificate = completeReview($assessment, $assessor);
    $pdfFileId = $certificate->pdf_file_id;
    $pdf = storedFileBytes((int) $pdfFileId);

    uploadEntityLogo($owner, logoUpload(logoImageBytes('jpeg'), 'شعار-محدث.jpg'))->assertCreated();

    expect($certificate->refresh()->pdf_file_id)->toBe($pdfFileId)
        ->and(storedFileBytes((int) $pdfFileId))->toBe($pdf)
        ->and($pdf)->toContain(trim((string) json_encode(base64_encode(storedFileBytes($logoId))), '"'))
        ->and($certificate->entity_logo_file_id)->toBe($logoId);
});

it('renders a deferred certificate PDF with the logo kept at issue after the logo was replaced and removed', function (): void {
    $fake = app(FakePdfRenderer::class);
    app()->instance(PdfRenderer::class, new UnavailablePdfRenderer);
    ['assessment' => $assessment, 'assessor' => $assessor, 'owner' => $owner, 'logo_id' => $logoId] = scoredRequestWithLogo();
    $certificate = completeReview($assessment, $assessor);

    uploadEntityLogo($owner, logoUpload(logoImageBytes('jpeg'), 'شعار-محدث.jpg'))->assertCreated();
    switchTo($owner)->deleteJson('/api/v1/entities/me/logo')->assertOk()->assertJsonPath('data.logo', null);

    $kept = StoredFile::query()->findOrFail($logoId);
    expect($certificate->refresh()->pdf_file_id)->toBeNull()
        ->and($certificate->entity_logo_file_id)->toBe($logoId)
        ->and(Storage::disk('local')->exists($kept->path))->toBeTrue();

    app()->instance(PdfRenderer::class, $fake);
    switchTo($owner)->postJson("/api/v1/certificates/{$certificate->public_id}/download")->assertOk();

    expect(lastRenderedCertificate()->data['entity_logo'])->toBe(logoDataUri($logoId));
});

it('re-issues the certificate with the current logo and keeps the previous one on the superseded certificate', function (): void {
    ['assessment' => $assessment, 'assessor' => $assessor, 'owner' => $owner, 'logo_id' => $firstLogoId] = scoredRequestWithLogo();
    $first = completeReview($assessment, $assessor);
    uploadEntityLogo($owner, logoUpload(logoImageBytes('jpeg'), 'شعار-محدث.jpg'))->assertCreated();
    $currentLogoId = (int) Entity::query()->findOrFail($assessment->entity_id)->logo_file_id;

    $reissuedId = switchTo(systemAdmin())->postJson("/api/v1/certificates/{$first->public_id}/reissue", ['reason' => CERTIFICATE_REASON])
        ->assertCreated()
        ->json('data.id');
    $reissued = Certificate::query()->where('public_id', $reissuedId)->sole();

    expect($reissued->entity_logo_file_id)->toBe($currentLogoId)
        ->and($first->refresh()->entity_logo_file_id)->toBe($firstLogoId)
        ->and(StoredFile::query()->whereKey($firstLogoId)->exists())->toBeTrue()
        ->and(lastRenderedCertificate()->data['entity_logo'])->toBe(logoDataUri($currentLogoId, 'image/jpeg'));
});

it('refuses to change the logo kept by an issued certificate in the database', function (): void {
    ['assessment' => $assessment, 'assessor' => $assessor] = scoredRequestWithLogo();
    $certificate = completeReview($assessment, $assessor);

    expect(fn () => DB::table('certificates')->where('id', $certificate->id)->update(['entity_logo_file_id' => null]))->toThrow(QueryException::class);
});

it('keeps the logo of an issued certificate when the entity is permanently deleted', function (): void {
    ['assessment' => $assessment, 'assessor' => $assessor, 'logo_id' => $logoId] = scoredRequestWithLogo();
    completeReview($assessment, $assessor);
    $entity = Entity::query()->findOrFail($assessment->entity_id);
    $entity->delete();

    app(EntityEraser::class)->erase($entity->refresh());

    $kept = StoredFile::query()->findOrFail($logoId);
    expect(Entity::withTrashed()->findOrFail($entity->id)->logo_file_id)->toBeNull()
        ->and($kept->attachable_type)->not->toBeNull()
        ->and(Storage::disk('local')->exists($kept->path))->toBeTrue();
});
