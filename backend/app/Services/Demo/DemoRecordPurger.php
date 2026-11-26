<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Platform\DemoPurgeCount;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Models\Platform\DemoRecord;
use App\Support\Demo\DemoPurgeTally;

final readonly class DemoRecordPurger
{
    public function __construct(
        private DemoEntityPurger $entities,
        private DemoAssessmentPurger $assessments,
        private DemoUserPurger $users,
        private DemoFiles $files,
    ) {}

    /**
     * @return list<StoredFile>
     */
    public function purge(DemoRecord $record, DemoPurgeTally $tally): array
    {
        $removedFiles = match ($record->kind) {
            DemoRecordKind::Entity => $this->purgeEntity($record->record_id, $tally),
            DemoRecordKind::InsightsAssessment => $this->files->removeRecords($this->assessments->purge([$record->record_id], $tally)),
            DemoRecordKind::User => $this->purgeUser($record->record_id, $tally),
        };

        $tally->add(DemoPurgeCount::RegisterRecords, DemoRecord::query()->whereKey($record->id)->toBase()->delete());

        return $removedFiles;
    }

    /**
     * @return list<StoredFile>
     */
    private function purgeEntity(int $entityId, DemoPurgeTally $tally): array
    {
        $entity = Entity::withTrashed()->find($entityId);

        return $entity instanceof Entity ? $this->entities->purge($entity, $tally) : [];
    }

    /**
     * @return list<StoredFile>
     */
    private function purgeUser(int $userId, DemoPurgeTally $tally): array
    {
        $user = User::withTrashed()->find($userId);

        if ($user instanceof User) {
            $this->users->purge($user, $tally);
        }

        return [];
    }
}
