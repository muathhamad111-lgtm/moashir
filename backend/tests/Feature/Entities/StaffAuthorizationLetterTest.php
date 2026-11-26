<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityDocument;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Identity\RoleKey;
use App\Events\Entities\EntityProfileUpdated;
use App\Models\Audit\Audit;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const STAFF_LETTER_NEW_PERSON = 'نورة بنت عبدالله القحطاني';
const STAFF_LETTER_REASON = 'تغيير المفوض بناءً على خطاب مجلس إدارة الجهة.';

beforeEach(function (): void {
    Notification::fake();
});

function staffLetterEntity(): Entity
{
    $entity = entityWithOwner();
    $letter = storedTestFile(['purpose' => EntityDocument::AuthorizationLetter->purpose(), 'malware_scan_status' => MalwareScanStatus::Clean, 'malware_scanned_at' => Date::now()]);
    $letter->attachable()->associate($entity)->save();
    $entity->forceFill([EntityDocument::AuthorizationLetter->column() => $letter->id])->save();

    return $entity->refresh();
}

function staffLetterFileOf(Entity $entity): ?StoredFile
{
    return $entity->refresh()->authorizationLetter;
}

/**
 * The staff edit as the SPA sends it with a file: a multipart POST that names the PUT method.
 *
 * @param  array<string, mixed>  $overrides
 */
function staffLetterEdit(Entity $entity, ?UploadedFile $letter, array $overrides = []): TestResponse
{
    $body = [...profilePayload($entity, ['founding_year' => $entity->founding_year, ...$overrides]), 'reason' => STAFF_LETTER_REASON, '_method' => 'PUT'];

    return test()->post("/api/v1/entities/{$entity->id}", $letter instanceof UploadedFile ? [...$body, 'authorization_letter' => $letter] : $body, ['Accept' => 'application/json']);
}

it('refuses a staff edit that names a new authorized person without a new authorization letter', function (): void {
    $entity = staffLetterEntity();
    $person = ownerOf($entity)->name;
    signIn(systemAdmin());

    $this->putJson("/api/v1/entities/{$entity->id}", [...profilePayload($entity, ['authorized_person_name' => STAFF_LETTER_NEW_PERSON, 'employees_count' => 60]), 'reason' => STAFF_LETTER_REASON])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'profile.change_authorization_letter_required')
        ->assertJsonValidationErrors(['authorization_letter']);

    expect(ownerOf($entity)->name)->toBe($person)
        ->and($entity->refresh()->employees_count)->not->toBe(60)
        ->and(Audit::query()->where('event', AuditEvent::EntityUpdatedByStaff->value)->exists())->toBeFalse();
});

it('changes the authorized person with the new letter, which replaces and removes the previous one', function (): void {
    $entity = staffLetterEntity();
    $previous = staffLetterFileOf($entity);
    $admin = systemAdmin();
    signIn($admin);

    staffLetterEdit($entity, entityDocumentPdf('خطاب-تفويض-جديد.pdf'), ['authorized_person_name' => STAFF_LETTER_NEW_PERSON])
        ->assertOk()
        ->assertJsonPath('data.authorized_person_name', STAFF_LETTER_NEW_PERSON)
        ->assertJsonPath('data.documents.authorization_letter.name', 'خطاب-تفويض-جديد.pdf');

    $letter = staffLetterFileOf($entity);

    expect(ownerOf($entity)->name)->toBe(STAFF_LETTER_NEW_PERSON)
        ->and($letter?->id)->not->toBe($previous?->id)
        ->and($letter?->purpose)->toBe(EntityDocument::AuthorizationLetter->purpose())
        ->and($letter?->uploaded_by_user_id)->toBe($admin->id)
        ->and($letter?->attachable_id)->toBe($entity->id)
        ->and(StoredFile::query()->find($previous?->id))->toBeNull()
        ->and(Audit::query()->where('event', AuditEvent::EntityDocumentUploaded->value)->where('auditable_id', $entity->id)->exists())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::EntityUpdatedByStaff->value)->value('reason'))->toBe(STAFF_LETTER_REASON);
});

it('lets staff replace the letter while the authorized person stays the same', function (): void {
    $entity = staffLetterEntity();
    $previous = staffLetterFileOf($entity);
    signIn(systemAdmin());

    staffLetterEdit($entity, entityDocumentPdf('خطاب-محدث.pdf'))->assertOk()->assertJsonPath('data.documents.authorization_letter.name', 'خطاب-محدث.pdf');

    expect(staffLetterFileOf($entity)?->id)->not->toBe($previous?->id);
});

it('keeps the letter when staff edit other data without changing the person', function (): void {
    $entity = staffLetterEntity();
    $previous = staffLetterFileOf($entity);
    signIn(systemAdmin());

    staffLetterEdit($entity, null, ['employees_count' => 61])->assertOk()->assertJsonPath('data.employees_count', 61);

    expect(staffLetterFileOf($entity)?->id)->toBe($previous?->id);
});

it('refuses a letter that is not a PDF or is over the size limit', function (UploadedFile $letter): void {
    $entity = staffLetterEntity();
    $person = ownerOf($entity)->name;
    $files = StoredFile::query()->count();
    signIn(systemAdmin());

    staffLetterEdit($entity, $letter, ['authorized_person_name' => STAFF_LETTER_NEW_PERSON])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['authorization_letter']);

    expect(ownerOf($entity)->name)->toBe($person)->and(StoredFile::query()->count())->toBe($files);
})->with([
    'image' => [fn (): UploadedFile => UploadedFile::fake()->image('خطاب.png')],
    'over 5 MB' => [fn (): UploadedFile => UploadedFile::fake()->create('خطاب.pdf', 5121, 'application/pdf')],
]);

it('removes the taken-in letter when the edit fails before it commits', function (): void {
    $entity = staffLetterEntity();
    $previous = staffLetterFileOf($entity);
    $person = ownerOf($entity)->name;
    signIn(systemAdmin());
    User::updating(static fn () => throw new RuntimeException('Simulated failure inside the edit.'));

    staffLetterEdit($entity, entityDocumentPdf('خطاب-تفويض-جديد.pdf'), ['authorized_person_name' => STAFF_LETTER_NEW_PERSON])->assertServerError();

    expect(staffLetterFileOf($entity)?->id)->toBe($previous?->id)
        ->and(ownerOf($entity)->name)->toBe($person)
        ->and(StoredFile::query()->where('original_name', 'خطاب-تفويض-جديد.pdf')->exists())->toBeFalse();
});

it('keeps the committed letter when a step after the commit fails', function (): void {
    $entity = staffLetterEntity();
    signIn(systemAdmin());
    Event::listen(EntityProfileUpdated::class, static fn () => throw new RuntimeException('Simulated failure after the commit.'));

    staffLetterEdit($entity, entityDocumentPdf('خطاب-تفويض-جديد.pdf'), ['authorized_person_name' => STAFF_LETTER_NEW_PERSON, 'employees_count' => 62])->assertServerError();

    expect(staffLetterFileOf($entity)?->original_name)->toBe('خطاب-تفويض-جديد.pdf')
        ->and(ownerOf($entity)->name)->toBe(STAFF_LETTER_NEW_PERSON);
});

it('takes in no letter from a user who may not edit the entity', function (RoleKey $role): void {
    $entity = staffLetterEntity();
    $files = StoredFile::query()->count();
    signIn(staffUser($role));

    staffLetterEdit($entity, entityDocumentPdf('خطاب.pdf'), ['authorized_person_name' => STAFF_LETTER_NEW_PERSON])->assertForbidden();

    expect(StoredFile::query()->count())->toBe($files);
})->with([RoleKey::RequestFollower, RoleKey::FinanceOfficer]);
