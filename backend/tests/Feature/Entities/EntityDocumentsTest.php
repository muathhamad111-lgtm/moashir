<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityDocument;
use App\Enums\Entities\ProfileSection;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Entities\EntityEraser;
use App\Services\Files\SignedDownloadIssuer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $payload
 */
function registerWithDocuments(array $payload): TestResponse
{
    return test()->postJson('/api/v1/auth/register', $payload);
}

function documentedEntity(): Entity
{
    $entity = entityWithOwner();

    foreach (EntityDocument::required() as $document) {
        $file = storedTestFile(['purpose' => $document->purpose(), 'malware_scan_status' => MalwareScanStatus::Clean, 'malware_scanned_at' => Date::now(), 'original_name' => $document->value.'.pdf']);
        $file->attachable()->associate($entity)->save();
        $entity->forceFill([$document->column() => $file->id]);
    }

    $entity->save();

    return $entity->refresh();
}

function entityDocumentFileOf(Entity $entity, EntityDocument $document): StoredFile
{
    return StoredFile::query()->findOrFail($entity->refresh()->getAttribute($document->column()));
}

function documentLinkFor(User $viewer, Entity $entity, EntityDocument $document): string
{
    return app(SignedDownloadIssuer::class)->issue(entityDocumentFileOf($entity, $document), $viewer)->url;
}

it('stores both registration documents as private class-C files attached to the new entity', function (): void {
    registerWithDocuments(registrationPayload())->assertCreated();

    $owner = User::query()->where('email', 'info@alamal.example.sa')->firstOrFail();
    $entity = Entity::query()->findOrFail($owner->entity_id);

    foreach (EntityDocument::required() as $document) {
        $file = entityDocumentFileOf($entity, $document);

        expect($file->purpose)->toBe($document->purpose())
            ->and($file->content_class)->toBe('C')
            ->and($file->visibility)->toBe(FileVisibility::Private)
            ->and($file->purpose->isWatermarked())->toBeFalse()
            ->and($file->malware_scan_status)->toBe(MalwareScanStatus::Clean)
            ->and($file->attachable_id)->toBe($entity->id)
            ->and($file->uploaded_by_user_id)->toBe($owner->id)
            ->and($file->path)->toMatch('#^files/entity-'.str_replace('_', '-', $document->value).'/\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#');
    }

    expect($entity->license_number)->toBe('447001234567')
        ->and(Audit::query()->where('event', AuditEvent::EntityDocumentUploaded->value)->count())->toBe(2);
});

it('refuses a registration without a document, a licence number, a PDF or within the size limit and keeps no file', function (array $override, string $field): void {
    registerWithDocuments(registrationPayload($override))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Entity::query()->count())->toBe(0)
        ->and(StoredFile::withTrashed()->count())->toBe(0);
})->with([
    'no licence certificate' => [['license_certificate' => null], 'license_certificate'],
    'no authorization letter' => [['authorization_letter' => null], 'authorization_letter'],
    'no licence number' => [['license_number' => null], 'license_number'],
    'an image as the licence certificate' => [['license_certificate' => UploadedFile::fake()->image('ترخيص.png', 400, 400)], 'license_certificate'],
    'a Word file as the letter' => [['authorization_letter' => UploadedFile::fake()->create('خطاب.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')], 'authorization_letter'],
    'a letter larger than 5 MB' => [['authorization_letter' => UploadedFile::fake()->createWithContent('خطاب.pdf', SAMPLE_PDF.str_repeat('%', 5200 * 1024))], 'authorization_letter'],
]);

it('refuses a password-locked or disguised PDF before the entity is created', function (Closure $contents): void {
    registerWithDocuments(registrationPayload(['license_certificate' => entityDocumentPdf('ترخيص.pdf', $contents())]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['license_certificate']);

    expect(Entity::query()->count())->toBe(0)
        ->and(StoredFile::query()->count())->toBe(0);
})->with([
    'locked with a password' => [passwordProtectedPdf(...)],
    'text renamed to PDF' => [static fn (): string => 'شهادة ترخيص بصيغة نصية'],
]);

it('refuses an infected document, reports it and leaves no file behind', function (): void {
    registerWithDocuments(registrationPayload(['authorization_letter' => entityDocumentPdf('خطاب.pdf', SAMPLE_PDF.FakeFileScanner::TEST_SIGNATURE)]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.infected');

    expect(Entity::query()->count())->toBe(0)
        ->and(StoredFile::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('files/entity-license-certificate'))->toBe([])
        ->and(Storage::disk('local')->allFiles('files/entity-authorization-letter'))->toBe([])
        ->and(Audit::query()->where('event', AuditEvent::InfectedUploadRefused->value)->count())->toBe(1);
});

it('removes the scanned documents when the registration itself fails', function (): void {
    User::factory()->create(['email' => 'info@alamal.example.sa']);

    registerWithDocuments(registrationPayload())->assertUnprocessable();

    expect(Entity::query()->count())->toBe(0)
        ->and(StoredFile::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('files/entity-license-certificate'))->toBe([]);
});

it('shows the documents on the entity record to entity managers only', function (): void {
    $entity = documentedEntity();

    switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}")->assertOk()
        ->assertJsonPath('data.documents.license_certificate.name', 'license_certificate.pdf')
        ->assertJsonPath('data.documents.authorization_letter.name', 'authorization_letter.pdf')
        ->assertJsonPath('data.documents.vat_certificate', null);

    switchTo(financeOfficer())->getJson("/api/v1/entities/{$entity->id}")->assertOk()->assertJsonMissingPath('data.documents');
    switchTo(systemAdmin())->getJson('/api/v1/entities')->assertOk()->assertJsonMissingPath('data.0.documents');
});

it('shows the entity its own documents and null for a missing one', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->getJson('/api/v1/entities/me')->assertOk()
        ->assertJsonPath('data.documents', ['license_certificate' => null, 'authorization_letter' => null, 'vat_certificate' => null]);
});

it('lets the owner and entity managers open a document as uploaded, and audits each view', function (): void {
    $entity = documentedEntity();
    $owner = ownerOf($entity);

    $ownUrl = (string) switchTo($owner)->postJson('/api/v1/entities/me/documents/license_certificate/download')->assertOk()->json('data.url');
    expect(switchTo($owner)->get($ownUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf')->streamedContent())->toBe(SAMPLE_PDF);

    $admin = systemAdmin();
    $staffUrl = (string) switchTo($admin)->postJson("/api/v1/entities/{$entity->id}/documents/authorization_letter/download")->assertOk()->json('data.url');
    expect(switchTo($admin)->get($staffUrl)->assertOk()->streamedContent())->toBe(SAMPLE_PDF)
        ->and(Audit::query()->where('event', AuditEvent::FileDownloaded->value)->count())->toBe(2);
});

it('keeps the documents from other entities and from staff who do not manage entities', function (): void {
    $entity = documentedEntity();
    $stranger = ownerOf(entityWithOwner());
    $finance = financeOfficer();

    switchTo($stranger)->get(documentLinkFor($stranger, $entity, EntityDocument::LicenseCertificate))->assertNotFound();
    switchTo($finance)->get(documentLinkFor($finance, $entity, EntityDocument::LicenseCertificate))->assertForbidden();
    switchTo($finance)->postJson("/api/v1/entities/{$entity->id}/documents/license_certificate/download")->assertForbidden();
    switchTo($stranger)->postJson("/api/v1/entities/{$entity->id}/documents/license_certificate/download")->assertNotFound();
});

it('answers 404 for a document the entity does not have', function (): void {
    $entity = entityWithOwner();

    switchTo(ownerOf($entity))->postJson('/api/v1/entities/me/documents/authorization_letter/download')
        ->assertNotFound()
        ->assertJsonPath('code', 'entity.document_missing');
    switchTo(systemAdmin())->postJson("/api/v1/entities/{$entity->id}/documents/vat_certificate/download")->assertNotFound();
});

it('replaces a document through an approved profile change and removes the previous file', function (): void {
    $entity = documentedEntity();
    $owner = ownerOf($entity);
    $previous = entityDocumentFileOf($entity, EntityDocument::LicenseCertificate);

    uploadOwnDocument($owner, EntityDocument::LicenseCertificate, entityDocumentPdf('ترخيص-مجدد.pdf'))->assertAccepted();

    expect(pendingProfileChangeOf($entity)?->items())->toBe(['license_certificate'])
        ->and(entityDocumentFileOf($entity, EntityDocument::LicenseCertificate)->id)->toBe($previous->id);

    approveOwnPendingChange($owner)->assertOk()->assertJsonPath('data.documents.license_certificate.name', 'ترخيص-مجدد.pdf');

    expect(StoredFile::query()->find($previous->id))->toBeNull();
});

it('gives a missing document through a profile change', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    uploadOwnDocument($owner, EntityDocument::AuthorizationLetter)->assertAccepted();
    approveOwnPendingChange($owner)->assertOk()->assertJsonPath('data.documents.authorization_letter.name', 'authorization_letter.pdf');
});

it('refuses a non-PDF document, the VAT certificate on the documents route and staff uploads', function (): void {
    $owner = ownerOf(entityWithOwner());

    uploadOwnDocument($owner, EntityDocument::LicenseCertificate, UploadedFile::fake()->image('ترخيص.png'))->assertUnprocessable()->assertJsonValidationErrors(['file']);
    uploadOwnDocument($owner, EntityDocument::VatCertificate)->assertNotFound();
    uploadOwnDocument(systemAdmin(), EntityDocument::LicenseCertificate)->assertForbidden();
});

it('asks for a new authorization letter with a new authorized person', function (): void {
    $entity = documentedEntity();

    saveSection($entity, ProfileSection::Contact, ['authorized_person_name' => 'ريم بنت خالد الحربي'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'profile.change_authorization_letter_required')
        ->assertJsonValidationErrors(['authorization_letter']);

    expect(pendingProfileChangeOf($entity))->toBeNull();
});

it('approves a new authorized person only together with the new letter', function (): void {
    $entity = documentedEntity();
    $owner = ownerOf($entity);
    $previous = entityDocumentFileOf($entity, EntityDocument::AuthorizationLetter);

    uploadOwnDocument($owner, EntityDocument::AuthorizationLetter, entityDocumentPdf('خطاب-ريم.pdf'))->assertAccepted();
    saveSection($entity, ProfileSection::Contact, ['authorized_person_name' => 'ريم بنت خالد الحربي'])->assertAccepted();
    $pending = pendingProfileChangeOf($entity);

    decideProfileChange($pending, ['authorized_person_name'], 'الخطاب غير مختوم من الجهة.')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'profile.change_authorization_letter_required');
    decideProfileChange($pending, ['authorization_letter'], 'اسم الشخص المفوّض غير مطابق.')->assertUnprocessable();

    decideProfileChange($pending, ['authorized_person_name', 'authorization_letter'])->assertOk();

    expect($owner->refresh()->name)->toBe('ريم بنت خالد الحربي')
        ->and(entityDocumentFileOf($entity, EntityDocument::AuthorizationLetter)->original_name)->toBe('خطاب-ريم.pdf')
        ->and(StoredFile::query()->find($previous->id))->toBeNull();
});

it('lets staff reject a new authorized person that came without a letter before the rule', function (): void {
    $entity = documentedEntity();
    saveSection($entity, ProfileSection::Contact, ['phone' => '0569876543'])->assertAccepted();
    $pending = pendingProfileChangeOf($entity);
    $pending?->proposeFields(ProfileSection::Contact->historyFields(), ['authorized_person_name' => 'ريم بنت خالد الحربي', 'phone' => '+966569876543'], ['authorized_person_name' => ownerOf($entity)->name, 'phone' => null]);
    $pending?->save();

    decideProfileChange($pending->refresh(), ['authorized_person_name', 'phone'])->assertUnprocessable();
    decideProfileChange($pending, ['phone'], 'أرفقوا خطاب تفويض للشخص الجديد.')->assertOk();

    expect(ownerOf($entity)->name)->not->toBe('ريم بنت خالد الحربي');
});

it('removes the documents when the entity is erased', function (): void {
    $entity = documentedEntity();
    $files = array_map(static fn (EntityDocument $document): StoredFile => entityDocumentFileOf($entity, $document), EntityDocument::required());
    $entity->delete();

    app(EntityEraser::class)->erase($entity->refresh());

    $erased = Entity::withTrashed()->findOrFail($entity->id);
    expect($erased->license_certificate_file_id)->toBeNull()
        ->and($erased->authorization_letter_file_id)->toBeNull()
        ->and(StoredFile::query()->whereKey(array_map(static fn (StoredFile $file): int => $file->id, $files))->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::EntityDocumentRemoved->value)->count())->toBe(2);
});

it('drops the document columns and purposes on rollback and restores them when it runs again', function (): void {
    $migration = require database_path('migrations/2026_11_26_120000_add_documents_to_entities_table.php');

    $migration->down();

    expect(Schema::hasColumns('entities', ['license_certificate_file_id', 'authorization_letter_file_id']))->toBeFalse()
        ->and(Schema::hasColumn('entity_profile_change_requests', 'pending_authorization_letter_file_id'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumns('entities', ['license_certificate_file_id', 'authorization_letter_file_id']))->toBeTrue()
        ->and(Schema::hasColumns('entity_profile_change_requests', ['pending_license_certificate_file_id', 'removes_authorization_letter']))->toBeTrue()
        ->and(storedTestFile(['purpose' => StoredFilePurpose::EntityAuthorizationLetter])->exists)->toBeTrue();
});

it('refuses to roll back while documents are stored', function (): void {
    documentedEntity();
    $migration = require database_path('migrations/2026_11_26_120000_add_documents_to_entities_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});
