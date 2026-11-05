<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityLogoReleaser;
use App\Services\Entities\OwnEntity;
use Illuminate\Support\Facades\DB;

final readonly class RemoveOwnEntityLogo
{
    public function __construct(
        private OwnEntity $ownEntity,
        private EntityLogoReleaser $releaser,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $owner): Entity
    {
        $entity = $this->ownEntity->editableOf($owner, 'remove_logo');

        DB::transaction(function () use ($entity): void {
            $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();
            $logo = $locked->logo()->withTrashed()->first();

            if ($logo instanceof StoredFile) {
                $this->detach($locked, $logo);
            }
        });

        return $entity->refresh();
    }

    private function detach(Entity $locked, StoredFile $logo): void
    {
        $locked->forceFill(['logo_file_id' => null])->save();

        $this->audit->record(new AuditEntry(AuditEvent::EntityLogoRemoved, $locked, ['logo_file_id' => $logo->public_id], ['logo_file_id' => null]));

        $this->releaser->release($logo);
    }
}
