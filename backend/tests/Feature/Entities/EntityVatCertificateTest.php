<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\ProfileSection;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\StoredFilePurpose;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Services\Entities\EntityEraser;
use App\Services\Files\SignedDownloadIssuer;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
});

function currentVatCertificateOf(Entity $entity): StoredFile
{
    return StoredFile::query()->findOrFail($entity->refresh()->vat_certificate_file_id);
}

it('keeps an uploaded PDF certificate private, audits it and completes the billing data', function (): void {
    $entity = vatRegisteredEntity();

    $response = uploadAndApproveVatCertificate(ownerOf($entity), vatCertificatePdf())
        ->assertOk()
        ->assertJsonPath('data.billing_complete', true)
        ->assertJsonPath('data.profile_completion.percent', 100)
        ->assertJsonPath('data.vat_certificate.name', 'شهادة-التسجيل-الضريبي.pdf');

    $certificate = currentVatCertificateOf($entity);
    expect($response->json('data.vat_certificate.id'))->toBe($certificate->public_id)
        ->and($certificate->purpose)->toBe(StoredFilePurpose::VatCertificate)
        ->and($certificate->visibility)->toBe(FileVisibility::Private)
        ->and($certificate->attachable_id)->toBe($entity->id)
        ->and($certificate->path)->toMatch('#^files/vat-certificate/\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#')
        ->and(Audit::query()->where('event', AuditEvent::EntityVatCertificateUploaded->value)->sole()->new_values)
        ->toMatchArray(['vat_certificate_file_id' => $certificate->public_id, 'mime_type' => 'application/pdf']);
});

it('replaces the previous certificate and removes its file', function (): void {
    $entity = vatRegisteredEntity();
    $owner = ownerOf($entity);
    uploadAndApproveVatCertificate($owner, vatCertificatePdf())->assertOk();
    $first = currentVatCertificateOf($entity);

    uploadAndApproveVatCertificate($owner, vatCertificatePdf('شهادة-محدثة.pdf', SAMPLE_PDF.'%updated'))->assertOk();

    expect(currentVatCertificateOf($entity)->id)->not->toBe($first->id)
        ->and(StoredFile::query()->find($first->id))->toBeNull();
});

it('refuses anything but a PDF within the size limit', function (Closure $file): void {
    $entity = vatRegisteredEntity();

    uploadVatCertificate(ownerOf($entity), $file())->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect($entity->refresh()->vat_certificate_file_id)->toBeNull()
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::VatCertificate)->count())->toBe(0);
})->with([
    'PNG image' => [fn (): UploadedFile => UploadedFile::fake()->image('شهادة.png', 400, 400)],
    'text renamed to PDF' => [fn (): UploadedFile => vatCertificatePdf('شهادة.pdf', 'شهادة التسجيل الضريبي بصيغة نصية')],
    'PDF locked with a password' => [fn (): UploadedFile => vatCertificatePdf('شهادة.pdf', passwordProtectedPdf())],
    'larger than the limit' => [fn (): UploadedFile => vatCertificatePdf('شهادة.pdf', SAMPLE_PDF.str_repeat('%', 5200 * 1024))],
]);

it('asks for the certificate file', function (): void {
    switchTo(ownerOf(vatRegisteredEntity()))->post('/api/v1/entities/me/vat-certificate', [], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('never keeps an infected certificate and reports the attempt', function (): void {
    $entity = vatRegisteredEntity();

    uploadVatCertificate(ownerOf($entity), vatCertificatePdf('شهادة.pdf', SAMPLE_PDF.FakeFileScanner::TEST_SIGNATURE))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.infected');

    expect($entity->refresh()->vat_certificate_file_id)->toBeNull()
        ->and(Storage::disk('local')->allFiles('files/vat-certificate'))->toBe([])
        ->and(Audit::query()->where('event', AuditEvent::InfectedUploadRefused->value)->count())->toBe(1);
});

it('refuses a certificate while the entity is not registered for VAT', function (?bool $registered): void {
    $entity = entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->state(['vat_registered' => $registered]));

    uploadVatCertificate(ownerOf($entity), vatCertificatePdf())
        ->assertConflict()
        ->assertJsonPath('code', 'entity.vat_not_registered');

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::VatCertificate)->count())->toBe(0);
})->with(['answered no' => [false], 'unanswered' => [null]]);

it('removes the certificate on request and when the entity answers «no»', function (): void {
    $entity = vatRegisteredEntity();
    $owner = ownerOf($entity);
    uploadAndApproveVatCertificate($owner, vatCertificatePdf())->assertOk();
    $first = currentVatCertificateOf($entity);

    removeAndApproveVatCertificate($owner)->assertOk()
        ->assertJsonPath('data.vat_certificate', null)
        ->assertJsonPath('data.billing_complete', false);
    expect(StoredFile::query()->find($first->id))->toBeNull();

    uploadAndApproveVatCertificate($owner, vatCertificatePdf())->assertOk();
    $second = currentVatCertificateOf($entity);

    saveAndApproveSection($entity, ProfileSection::Billing, ['vat_registered' => false, 'vat_number' => null])->assertOk()
        ->assertJsonPath('data.vat_number', null)
        ->assertJsonPath('data.vat_certificate', null)
        ->assertJsonPath('data.billing_complete', true);

    expect($entity->refresh()->vat_certificate_file_id)->toBeNull()
        ->and(StoredFile::query()->find($second->id))->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::EntityVatCertificateRemoved->value)->count())->toBe(2);
});

it('issues a short-lived download link to the owner and to Finance, never to other entities or staff', function (): void {
    $entity = vatRegisteredEntity();
    $owner = ownerOf($entity);
    uploadAndApproveVatCertificate($owner, vatCertificatePdf())->assertOk();

    $url = (string) switchTo($owner)->postJson('/api/v1/entities/me/vat-certificate/download')->assertOk()->json('data.url');
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $finance = financeOfficer();
    $certificate = currentVatCertificateOf($entity);
    $financeUrl = app(SignedDownloadIssuer::class)->issue($certificate, $finance)->url;
    switchTo($finance)->get($financeUrl)->assertOk();

    $stranger = ownerOf(vatRegisteredEntity());
    switchTo($stranger)->get(app(SignedDownloadIssuer::class)->issue($certificate, $stranger)->url)->assertNotFound();
    $staff = contentOfficer();
    switchTo($staff)->get(app(SignedDownloadIssuer::class)->issue($certificate, $staff)->url)->assertNotFound();
});

it('answers 404 when there is no certificate to download', function (): void {
    switchTo(ownerOf(vatRegisteredEntity()))->postJson('/api/v1/entities/me/vat-certificate/download')
        ->assertNotFound()
        ->assertJsonPath('code', 'entity.vat_certificate_missing');
});

it('keeps staff away from the own certificate endpoints', function (): void {
    $staff = systemAdmin();

    switchTo($staff)->post('/api/v1/entities/me/vat-certificate', ['file' => vatCertificatePdf()], ['Accept' => 'application/json'])->assertForbidden();
    switchTo($staff)->deleteJson('/api/v1/entities/me/vat-certificate')->assertForbidden();
    switchTo($staff)->postJson('/api/v1/entities/me/vat-certificate/download')->assertForbidden();
});

it('drops the certificate and the billing data when the entity is erased', function (): void {
    $entity = vatRegisteredEntity();
    uploadAndApproveVatCertificate(ownerOf($entity), vatCertificatePdf())->assertOk();
    $certificate = currentVatCertificateOf($entity);
    $entity->delete();

    app(EntityEraser::class)->erase($entity->refresh());

    $erased = Entity::withTrashed()->findOrFail($entity->id);
    expect($erased->vat_certificate_file_id)->toBeNull()
        ->and($erased->vat_number)->toBeNull()
        ->and($erased->address_street)->toBeNull()
        ->and(StoredFile::query()->find($certificate->id))->toBeNull();
});
