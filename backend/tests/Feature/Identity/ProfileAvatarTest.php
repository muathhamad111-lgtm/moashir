<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Entities\ErasedOwnerAnonymizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Notification::fake();
});

function uploadAvatar(User $user, UploadedFile $file): TestResponse
{
    return switchTo($user)->post('/api/v1/me/avatar', ['file' => $file], ['Accept' => 'application/json']);
}

function avatarPhoto(string $type = 'png', int $width = 400, int $height = 300): UploadedFile
{
    return logoUpload(logoImageBytes($type, $width, $height), 'صورة-شخصية.'.($type === 'png' ? 'png' : 'jpg'));
}

function currentAvatarOf(User $user): StoredFile
{
    return StoredFile::query()->findOrFail($user->refresh()->avatar_file_id);
}

it('re-encodes an uploaded photo, keeps it private under a random name and links it from the session', function (): void {
    $user = staffUser(RoleKey::Assessor);

    $response = uploadAvatar($user, avatarPhoto())->assertCreated()->assertJsonPath('data.avatar.source', 'photo');

    $photo = currentAvatarOf($user);
    expect($response->json('data.avatar.url'))->toBe('/api/v1/me/avatar?v='.$photo->public_id)
        ->and($photo->purpose)->toBe(StoredFilePurpose::UserAvatar)
        ->and($photo->visibility)->toBe(FileVisibility::Private)
        ->and($photo->content_class)->toBe('C')
        ->and($photo->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($photo->attachable_type)->toBe($user->getMorphClass())
        ->and($photo->attachable_id)->toBe($user->id)
        ->and($photo->path)->toMatch('#^files/user-avatar/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#')
        ->and($photo->original_name)->toBe('avatar.png')
        ->and(StoredFile::onlyTrashed()->ofPurpose(StoredFilePurpose::UserAvatar)->count())->toBe(1);

    switchTo($user)->getJson('/api/v1/auth/session')->assertOk()
        ->assertJsonPath('data.avatar', ['source' => 'photo', 'url' => '/api/v1/me/avatar?v='.$photo->public_id]);

    $image = $this->get((string) $response->json('data.avatar.url'))->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect((string) $image->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $image->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and(getimagesizefromstring($image->streamedContent()))->toMatchArray([0 => 400, 1 => 300]);
});

it('strips hidden metadata from the photo', function (): void {
    $user = systemAdmin();
    $original = jpegWithComment(logoImageBytes('jpeg'), LOGO_HIDDEN_NOTE);

    uploadAvatar($user, logoUpload($original, 'صورة.jpg'))->assertCreated();

    $photo = currentAvatarOf($user);
    expect($original)->toContain(LOGO_HIDDEN_NOTE)
        ->and(storedFileBytes($photo->id))->not->toContain(LOGO_HIDDEN_NOTE)
        ->and($photo->mime_type)->toBe('image/jpeg');
});

it('refuses anything but a PNG or JPEG photo of the allowed size', function (Closure $file): void {
    $user = systemAdmin();

    uploadAvatar($user, $file())->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect($user->refresh()->avatar_file_id)->toBeNull()
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::UserAvatar)->count())->toBe(0);
})->with([
    'SVG' => [fn (): UploadedFile => logoUpload('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"></svg>', 'صورة.svg')],
    'GIF' => [fn (): UploadedFile => UploadedFile::fake()->image('صورة.gif', 400, 400)],
    'larger than 2 MB' => [fn (): UploadedFile => logoUpload(pngWithTextChunk(logoImageBytes(), str_repeat('A', 2100 * 1024)))],
    'narrower than 200 px' => [fn (): UploadedFile => avatarPhoto('png', 150, 300)],
    'wider than 2000 px' => [fn (): UploadedFile => avatarPhoto('png', 2100, 400)],
    'text named PNG' => [fn (): UploadedFile => logoUpload('نص عادي وليس صورة', 'صورة.png')],
]);

it('asks for the photo file', function (): void {
    switchTo(systemAdmin())->post('/api/v1/me/avatar', [], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['file']);
});

it('never keeps an infected photo', function (): void {
    $user = systemAdmin();

    uploadAvatar($user, logoUpload(logoImageBytes().FakeFileScanner::TEST_SIGNATURE))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.infected');

    expect($user->refresh()->avatar_file_id)->toBeNull()
        ->and(Storage::disk('local')->allFiles('files/user-avatar'))->toBe([]);
});

it('audits the upload without the file content and replaces the previous photo', function (): void {
    $user = systemAdmin();
    uploadAvatar($user, avatarPhoto())->assertCreated();
    $first = currentAvatarOf($user);

    uploadAvatar($user, avatarPhoto('jpeg'))->assertCreated();
    $second = currentAvatarOf($user);

    $audits = Audit::query()->where('event', AuditEvent::UserAvatarUploaded->value)->oldest('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->old_values)->toBe(['avatar_file_id' => null])
        ->and($audits[1]->old_values)->toBe(['avatar_file_id' => $first->public_id])
        ->and($audits[1]->new_values)->toEqual(['avatar_file_id' => $second->public_id, 'mime_type' => 'image/jpeg', 'size_bytes' => $second->size_bytes, 'sha256' => $second->sha256])
        ->and(StoredFile::withTrashed()->findOrFail($first->id)->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($first->path))->toBeFalse();
});

it('removes the photo idempotently, audits only a real removal and falls back to no avatar', function (): void {
    $user = staffUser(RoleKey::FinanceOfficer);
    uploadAvatar($user, avatarPhoto())->assertCreated();
    $photo = currentAvatarOf($user);

    switchTo($user)->deleteJson('/api/v1/me/avatar')->assertOk()->assertJsonPath('data.avatar', null);
    switchTo($user)->deleteJson('/api/v1/me/avatar')->assertOk()->assertJsonPath('data.avatar', null);

    expect($user->refresh()->avatar_file_id)->toBeNull()
        ->and(StoredFile::withTrashed()->findOrFail($photo->id)->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($photo->path))->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::UserAvatarRemoved->value)->count())->toBe(1);
    switchTo($user)->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.avatar', null);
    $this->get('/api/v1/me/avatar')->assertNotFound();
});

it('requires a session for every avatar endpoint', function (): void {
    $this->post('/api/v1/me/avatar', ['file' => avatarPhoto()], ['Accept' => 'application/json'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
    $this->deleteJson('/api/v1/me/avatar')->assertUnauthorized();
    $this->get('/api/v1/me/avatar')->assertUnauthorized();
});

it('refuses a personal photo to entity accounts', function (): void {
    $owner = ownerOf(entityWithOwner());

    uploadAvatar($owner, avatarPhoto())->assertForbidden()->assertJsonPath('code', 'forbidden');
    switchTo($owner)->deleteJson('/api/v1/me/avatar')->assertForbidden();

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::UserAvatar)->count())->toBe(0);
});

it('shows and removes only the signed-in user\'s own photo', function (): void {
    $owner = systemAdmin();
    $other = staffUser(RoleKey::Assessor);
    uploadAvatar($owner, avatarPhoto())->assertCreated();
    $photo = currentAvatarOf($owner);

    switchTo($other)->get('/api/v1/me/avatar?v='.$photo->public_id)->assertNotFound();
    switchTo($other)->deleteJson('/api/v1/me/avatar')->assertOk();

    expect($owner->refresh()->avatar_file_id)->toBe($photo->id)
        ->and(Audit::query()->where('event', AuditEvent::UserAvatarRemoved->value)->count())->toBe(0);
    switchTo($other)->getJson('/api/v1/auth/session')->assertJsonPath('data.avatar', null);
});

it('uses the entity logo as the avatar of an entity account and falls back to none without a logo', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);

    switchTo($owner)->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.avatar', null);
    switchTo($owner)->get('/api/v1/me/avatar')->assertNotFound();

    uploadAndApproveLogo($owner, logoUpload(logoImageBytes()))->assertOk();
    $logo = StoredFile::query()->findOrFail($entity->refresh()->logo_file_id);

    $url = (string) switchTo($owner)->getJson('/api/v1/auth/session')->assertOk()
        ->assertJsonPath('data.avatar.source', 'entity_logo')
        ->json('data.avatar.url');

    expect($url)->toBe('/api/v1/me/avatar?v='.$logo->public_id);
    $image = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(getimagesizefromstring($image->streamedContent()))->toMatchArray([0 => 400, 1 => 300]);
});

it('drops the photo when the account is anonymised', function (): void {
    $user = systemAdmin();
    uploadAvatar($user, avatarPhoto())->assertCreated();
    $photo = currentAvatarOf($user);

    app(ErasedOwnerAnonymizer::class)->anonymize($user->refresh(), 'PURGED-AVATAR');

    expect(User::withTrashed()->findOrFail($user->id)->avatar_file_id)->toBeNull()
        ->and(StoredFile::withTrashed()->findOrFail($photo->id)->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($photo->path))->toBeFalse();
});
