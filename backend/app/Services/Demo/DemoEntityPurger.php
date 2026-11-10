<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Platform\DemoPurgeCount;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EntityPeriodStatus;
use App\Models\Assessments\PeriodNotice;
use App\Models\Contact\ContactMessage;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\FrozenDeadline;
use App\Models\Entities\PrivacyRequest;
use App\Models\Files\StoredFile;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Models\Platform\DemoRecord;
use App\Services\Contact\ContactMessageDeletion;
use App\Support\Demo\DemoPurgeTally;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class DemoEntityPurger
{
    public function __construct(
        private DemoAssessmentPurger $assessments,
        private DemoPaymentPurger $payments,
        private DemoUserPurger $users,
        private DemoFiles $files,
        private ContactMessageDeletion $contactMessages,
    ) {}

    /**
     * @return list<StoredFile>
     */
    public function purge(Entity $entity, DemoPurgeTally $tally): array
    {
        $owners = User::withTrashed()->where('entity_id', $entity->id)->orderBy('id')->get();
        $ownerIds = $this->idsOf($owners->modelKeys());

        $fileIds = [
            ...$this->assessments->purge($this->idsOf(Assessment::query()->ownedBy($entity->id)->pluck('id')->all()), $tally),
            ...$this->payments->purgeOf($entity->id, $tally),
            ...$this->files->ofEntity($entity, $ownerIds),
        ];
        $this->purgeEntityRecords($entity, $ownerIds);
        Entity::withTrashed()->whereKey($entity->id)->toBase()->update(['logo_file_id' => null]);

        $removed = $this->files->removeRecords(array_values(array_unique($fileIds)));
        $tally->add(DemoPurgeCount::Files, count($removed));
        $this->purgeOwners($owners, $tally);
        $tally->add(DemoPurgeCount::Entities, ScalarValue::int(Entity::withTrashed()->whereKey($entity->id)->forceDelete()));

        return $removed;
    }

    /**
     * @param  list<int>  $ownerIds
     */
    private function purgeEntityRecords(Entity $entity, array $ownerIds): void
    {
        $messageIds = ContactMessage::query()
            ->where(static fn (Builder $messages): Builder => $messages->where('entity_id', $entity->id)->orWhereIn('user_id', $ownerIds))
            ->pluck('id')
            ->all();
        $this->contactMessages->delete($this->idsOf($messageIds));

        EmailChangeRequest::query()
            ->where(static fn (Builder $requests): Builder => $requests->where('entity_id', $entity->id)->orWhereIn('user_id', $ownerIds))
            ->toBase()
            ->delete();

        EntityProfileChange::query()->where('entity_id', $entity->id)->toBase()->delete();
        PrivacyRequest::query()->where('entity_id', $entity->id)->toBase()->delete();
        FrozenDeadline::query()->where('entity_id', $entity->id)->toBase()->delete();
        EntityPeriodStatus::query()->where('entity_id', $entity->id)->toBase()->delete();
        PeriodNotice::query()->where('entity_id', $entity->id)->toBase()->delete();
    }

    /**
     * @param  Collection<int, User>  $owners
     */
    private function purgeOwners(Collection $owners, DemoPurgeTally $tally): void
    {
        foreach ($owners as $owner) {
            $this->users->purge($owner, $tally);
        }

        $tally->add(DemoPurgeCount::RegisterRecords, ScalarValue::int(DemoRecord::query()->where('kind', DemoRecordKind::User)->whereIn('record_id', $owners->modelKeys())->delete()));
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<int>
     */
    private function idsOf(array $ids): array
    {
        return array_values(array_map(static fn (mixed $id): int => ScalarValue::int($id), $ids));
    }
}
