<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityLogoIntake;
use App\Services\Entities\EntityLogoReleaser;
use App\Services\Entities\OwnEntity;
use App\Services\Files\StoredFileRemover;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UploadOwnEntityLogo
{
    public function __construct(
        private OwnEntity $ownEntity,
        private EntityLogoIntake $intake,
        private EntityLogoReleaser $releaser,
        private StoredFileRemover $remover,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $owner, UploadedFile $upload): Entity
    {
        $entity = $this->ownEntity->editableOf($owner, 'upload_logo');
        $logo = $this->intake->accept($upload, $entity, $owner);

        try {
            DB::transaction(fn () => $this->attach($entity, $logo));
        } catch (Throwable $failure) {
            $this->remover->remove($logo);

            throw $failure;
        }

        return $entity->refresh();
    }

    private function attach(Entity $entity, StoredFile $logo): void
    {
        $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();
        $previous = $locked->logo()->withTrashed()->first();
        $locked->forceFill(['logo_file_id' => $logo->id])->save();

        $this->audit->record(new AuditEntry(AuditEvent::EntityLogoUploaded, $locked, ['logo_file_id' => $previous?->public_id], [
            'logo_file_id' => $logo->public_id,
            'mime_type' => $logo->mime_type,
            'size_bytes' => $logo->size_bytes,
            'sha256' => $logo->sha256,
        ]));

        if ($previous instanceof StoredFile) {
            $this->releaser->release($previous);
        }
    }
}
