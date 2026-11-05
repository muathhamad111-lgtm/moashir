<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\Role;
use App\Services\Entities\EntityEraser;
use App\Services\Files\SignedDownloadIssuer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Notification::fake();
});

function currentLogoOf(Entity $entity): StoredFile
{
    return StoredFile::query()->findOrFail($entity->refresh()->logo_file_id);
}

function corruptPng(): string
{
    $png = logoImageBytes();

    return substr($png, 0, 60).str_repeat("\x00", 400).substr($png, -12);
}

it('re-encodes an uploaded PNG logo, keeps it private under a random name and returns a short-lived link', function (): void {
    $entity = entityWithOwner();

    $response = uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))
        ->assertCreated()
        ->assertJsonPath('data.profile_completion.items.10', ['key' => 'logo', 'is_complete' => true, 'is_required' => false]);

    $logo = currentLogoOf($entity);
    expect($response->json('data.logo.id'))->toBe($logo->public_id)
        ->and($response->json('data.logo.uploaded_at'))->toBe($logo->created_at->toIso8601String())
        ->and($logo->purpose)->toBe(StoredFilePurpose::EntityLogo)
        ->and($logo->visibility)->toBe(FileVisibility::Private)
        ->and($logo->content_class)->toBe('I')
        ->and($logo->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($logo->attachable_id)->toBe($entity->id)
        ->and($logo->path)->toMatch('#^files/entity-logo/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#')
        ->and($logo->original_name)->toBe($entity->reference().'-logo.png')
        ->and(StoredFile::onlyTrashed()->ofPurpose(StoredFilePurpose::EntityLogo)->count())->toBe(1);

    $download = $this->get((string) $response->json('data.logo.url'))->assertOk()->assertHeader('Content-Type', 'image/png');

    expect(getimagesizefromstring($download->streamedContent()))->toMatchArray([0 => 400, 1 => 300]);
});

it('strips hidden metadata from PNG and JPEG logos', function (string $name, Closure $contents, string $mimeType): void {
    $entity = entityWithOwner();
    $original = $contents();

    uploadEntityLogo(ownerOf($entity), logoUpload($original, $name))->assertCreated();

    $logo = currentLogoOf($entity);
    $stored = storedFileBytes($logo->id);

    expect($original)->toContain(LOGO_HIDDEN_NOTE)
        ->and($stored)->not->toContain(LOGO_HIDDEN_NOTE)
        ->and($logo->mime_type)->toBe($mimeType)
        ->and(getimagesizefromstring($stored))->toMatchArray([0 => 400, 1 => 300]);
})->with([
    'PNG text chunk' => ['شعار-الجمعية.png', fn (): string => pngWithTextChunk(logoImageBytes(), LOGO_HIDDEN_NOTE), 'image/png'],
    'JPEG comment' => ['شعار-الجمعية.jpg', fn (): string => jpegWithComment(logoImageBytes('jpeg'), LOGO_HIDDEN_NOTE), 'image/jpeg'],
]);

it('refuses anything but a PNG or JPEG image of the allowed size', function (Closure $file, string $code): void {
    $entity = entityWithOwner();

    uploadEntityLogo(ownerOf($entity), $file())->assertUnprocessable()->assertJsonPath('code', $code)->assertJsonValidationErrors(['file']);

    expect($entity->refresh()->logo_file_id)->toBeNull()
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::EntityLogo)->count())->toBe(0);
})->with([
    'SVG' => [fn (): UploadedFile => logoUpload('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><script>alert(1)</script></svg>', 'شعار.svg'), 'validation_failed'],
    'SVG renamed to PNG' => [fn (): UploadedFile => logoUpload('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"></svg>', 'شعار.png'), 'file.content_mismatch'],
    'GIF' => [fn (): UploadedFile => UploadedFile::fake()->image('شعار.gif', 400, 400), 'validation_failed'],
    'larger than 2 MB' => [fn (): UploadedFile => logoUpload(pngWithTextChunk(logoImageBytes(), str_repeat('A', 2100 * 1024))), 'validation_failed'],
    'narrower than 200 px' => [fn (): UploadedFile => logoUpload(logoImageBytes('png', 150, 300)), 'validation_failed'],
    'wider than 2000 px' => [fn (): UploadedFile => logoUpload(logoImageBytes('png', 2100, 400)), 'validation_failed'],
    'text named PNG' => [fn (): UploadedFile => logoUpload('شعار جمعية الأمل الخيرية بصيغة نصية', 'شعار.png'), 'validation_failed'],
    'PNG header with broken pixels' => [fn (): UploadedFile => logoUpload(corruptPng()), 'file.content_mismatch'],
]);

it('asks for the logo file', function (): void {
    switchTo(ownerOf(entityWithOwner()))->post('/api/v1/entities/me/logo', [], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['file']);
});

it('never keeps or shows an infected logo and reports the attempt', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    uploadEntityLogo($owner, logoUpload(logoImageBytes().FakeFileScanner::TEST_SIGNATURE))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.infected');

    expect($entity->refresh()->logo_file_id)->toBeNull()
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::EntityLogo)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('files/entity-logo'))->toBe([])
        ->and(Audit::query()->where('event', AuditEvent::InfectedUploadRefused->value)->count())->toBe(1);
    switchTo($owner)->getJson('/api/v1/entities/me')->assertOk()->assertJsonPath('data.logo', null);
});

it('refuses the logo while the file scanner is unavailable', function (): void {
    fileScanner()->makeUnavailable();
    $entity = entityWithOwner();

    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertUnprocessable()->assertJsonPath('code', 'file.scan_failed');

    expect($entity->refresh()->logo_file_id)->toBeNull();
});

it('audits the logo upload without the file content', function (): void {
    $entity = entityWithOwner();
    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertCreated();

    $audit = Audit::query()->where('event', AuditEvent::EntityLogoUploaded->value)->sole();
    $logo = currentLogoOf($entity);

    expect($audit->old_values)->toBe(['logo_file_id' => null])
        ->and($audit->new_values)->toEqual(['logo_file_id' => $logo->public_id, 'mime_type' => 'image/png', 'size_bytes' => $logo->size_bytes, 'sha256' => $logo->sha256]);
});

it('replaces the logo and removes the previous file that no certificate keeps', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $firstUrl = (string) uploadEntityLogo($owner, logoUpload(logoImageBytes()))->json('data.logo.url');
    $first = currentLogoOf($entity);

    uploadEntityLogo($owner, logoUpload(logoImageBytes('jpeg'), 'شعار-محدث.jpg'))->assertCreated()->assertJsonPath('data.logo.id', currentLogoOf($entity)->public_id);

    $removed = StoredFile::withTrashed()->findOrFail($first->id);
    expect(currentLogoOf($entity)->mime_type)->toBe('image/jpeg')
        ->and($removed->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($first->path))->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::EntityLogoUploaded->value)->latest('id')->firstOrFail()->old_values)->toBe(['logo_file_id' => $first->public_id]);
    switchTo($owner)->get($firstUrl)->assertNotFound();
});

it('removes the logo idempotently and audits only a real removal', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    uploadEntityLogo($owner, logoUpload(logoImageBytes()))->assertCreated();
    $logo = currentLogoOf($entity);

    switchTo($owner)->deleteJson('/api/v1/entities/me/logo')->assertOk()
        ->assertJsonPath('data.logo', null)
        ->assertJsonPath('data.profile_completion.items.9.is_complete', false);
    switchTo($owner)->deleteJson('/api/v1/entities/me/logo')->assertOk()->assertJsonPath('data.logo', null);

    expect($entity->refresh()->logo_file_id)->toBeNull()
        ->and(StoredFile::withTrashed()->findOrFail($logo->id)->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($logo->path))->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::EntityLogoRemoved->value)->count())->toBe(1);
});

it('requires a session to change the logo', function (): void {
    $this->post('/api/v1/entities/me/logo', ['file' => logoUpload(logoImageBytes())], ['Accept' => 'application/json'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
    $this->deleteJson('/api/v1/entities/me/logo')->assertUnauthorized();
});

it('refuses the logo endpoints to staff and to an entity account without the profile edit permission', function (): void {
    signIn(systemAdmin());
    $this->post('/api/v1/entities/me/logo', ['file' => logoUpload(logoImageBytes())], ['Accept' => 'application/json'])->assertForbidden();
    $this->deleteJson('/api/v1/entities/me/logo')->assertForbidden();

    $entity = entityWithOwner();
    Role::findByName(RoleKey::Entity->value, 'web')->revokePermissionTo(PermissionKey::EntityProfileEdit->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertForbidden()->assertJsonPath('code', 'forbidden');
    switchTo(ownerOf($entity))->deleteJson('/api/v1/entities/me/logo')->assertForbidden();
});

it('serves the logo only to its own entity and to staff with the full entity view', function (): void {
    $entity = entityWithOwner();
    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertCreated();
    $logo = currentLogoOf($entity);
    $otherOwner = ownerOf(entityWithOwner());

    switchTo($otherOwner)->get(app(SignedDownloadIssuer::class)->issue($logo, $otherOwner)->url)->assertNotFound();

    $staffUrl = (string) switchTo(systemAdmin())->getJson("/api/v1/entities/{$entity->id}")->assertOk()
        ->assertJsonPath('data.logo.id', $logo->public_id)
        ->json('data.logo.url');
    $this->get($staffUrl)->assertOk()->assertHeader('Content-Type', 'image/png');

    switchTo(staffUser(RoleKey::FinanceOfficer))->getJson("/api/v1/entities/{$entity->id}")->assertOk()->assertJsonMissingPath('data.logo');
    $rows = collect(switchTo(systemAdmin())->getJson('/api/v1/entities')->assertOk()->json('data'));

    expect($rows->firstWhere('id', $entity->id)['logo']['id'] ?? null)->toBe($logo->public_id)
        ->and($rows->firstWhere('id', $otherOwner->entity_id))->toHaveKey('logo', null);
});

it('releases the logo files on permanent deletion unless a certificate keeps one', function (): void {
    $entity = entityWithOwner();
    uploadEntityLogo(ownerOf($entity), logoUpload(logoImageBytes()))->assertCreated();
    $logo = currentLogoOf($entity);
    $entity->delete();

    app(EntityEraser::class)->erase($entity->refresh());

    $erased = Entity::withTrashed()->findOrFail($entity->id);
    expect($erased->logo_file_id)->toBeNull()
        ->and($erased->license_number)->toBeNull()
        ->and($erased->founded_on)->toBeNull()
        ->and($erased->website_url)->toBeNull()
        ->and(StoredFile::query()->findOrFail($logo->id)->attachable_type)->toBeNull();
});
