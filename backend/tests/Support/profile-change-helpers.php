<?php

declare(strict_types=1);

use App\Enums\Entities\EntityDocument;
use App\Enums\Entities\ProfileChangeStatus;
use App\Enums\Entities\ProfileSection;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\User;
use Database\Factories\Entities\EntityFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

function vatCertificatePdf(string $name = 'شهادة-التسجيل-الضريبي.pdf', string $contents = SAMPLE_PDF): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function uploadVatCertificate(User $owner, UploadedFile $file): TestResponse
{
    return switchTo($owner)->post('/api/v1/entities/me/vat-certificate', ['file' => $file], ['Accept' => 'application/json']);
}

function uploadOwnDocument(User $owner, EntityDocument $document, ?UploadedFile $file = null): TestResponse
{
    return switchTo($owner)->post('/api/v1/entities/me/documents/'.$document->value, ['file' => $file ?? entityDocumentPdf($document->value.'.pdf')], ['Accept' => 'application/json']);
}

function vatRegisteredEntity(): Entity
{
    return entityWithOwner(static fn (EntityFactory $factory): EntityFactory => $factory->vatRegistered());
}

function pendingProfileChangeOf(Entity $entity): ?EntityProfileChangeRequest
{
    return EntityProfileChangeRequest::query()->where('entity_id', $entity->id)->where('status', ProfileChangeStatus::Pending)->first();
}

/**
 * @param  list<string>  $approved
 */
function decideProfileChange(EntityProfileChangeRequest $request, array $approved, ?string $reason = null, ?User $as = null): TestResponse
{
    return switchTo($as ?? systemAdmin())->postJson('/api/v1/admin/entity-profile-changes/'.$request->public_id.'/decide', [
        'revision' => $request->revision,
        'approved_items' => $approved,
        'reason' => $reason,
    ]);
}

/**
 * Approves every item of the entity's pending change request, as the approver would.
 */
function approveProfileChange(Entity $entity, ?User $as = null): TestResponse
{
    $pending = pendingProfileChangeOf($entity);

    if (! $pending instanceof EntityProfileChangeRequest) {
        throw new RuntimeException('The entity has no pending profile change.');
    }

    return decideProfileChange($pending, $pending->items(), null, $as);
}

/**
 * Saves a section as the entity, approves the change as staff and returns the entity's own profile.
 *
 * @param  array<string, mixed>  $overrides
 */
function saveAndApproveSection(Entity $entity, ProfileSection $section, array $overrides = []): TestResponse
{
    saveSection($entity, $section, $overrides)->assertAccepted();

    if (pendingProfileChangeOf($entity) instanceof EntityProfileChangeRequest) {
        approveProfileChange($entity)->assertOk();
    }

    return switchTo(ownerOf($entity))->getJson('/api/v1/entities/me');
}

function approveOwnPendingChange(User $owner): TestResponse
{
    $entity = Entity::query()->findOrFail($owner->entity_id);

    if (pendingProfileChangeOf($entity) instanceof EntityProfileChangeRequest) {
        approveProfileChange($entity)->assertOk();
    }

    return switchTo($owner)->getJson('/api/v1/entities/me');
}

function uploadAndApproveLogo(User $owner, UploadedFile $file): TestResponse
{
    uploadEntityLogo($owner, $file)->assertAccepted();

    return approveOwnPendingChange($owner);
}

function removeAndApproveLogo(User $owner): TestResponse
{
    switchTo($owner)->deleteJson('/api/v1/entities/me/logo')->assertAccepted();

    return approveOwnPendingChange($owner);
}

function uploadAndApproveVatCertificate(User $owner, UploadedFile $file): TestResponse
{
    uploadVatCertificate($owner, $file)->assertAccepted();

    return approveOwnPendingChange($owner);
}

function removeAndApproveVatCertificate(User $owner): TestResponse
{
    switchTo($owner)->deleteJson('/api/v1/entities/me/vat-certificate')->assertAccepted();

    return approveOwnPendingChange($owner);
}
