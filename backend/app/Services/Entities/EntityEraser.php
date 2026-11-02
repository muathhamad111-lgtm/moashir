<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\EntityErasureStep;
use App\Data\Entities\ErasureOutcome;
use App\Enums\Entities\ErasureRetainedRecord;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\PrivacyRequest;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Support\ValueObjects\EntityName;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class EntityEraser
{
    public const string TAG = 'entities.erasure-steps';

    private const string PSEUDONYM_PREFIX = 'ERASED-';

    /**
     * @var list<EntityErasureStep>
     */
    private array $steps;

    /**
     * @param  iterable<EntityErasureStep>  $steps
     */
    public function __construct(
        #[Tag(self::TAG)] iterable $steps,
        private ErasedOwnerAnonymizer $ownerAnonymizer,
        private EntityDeadlineFreezer $deadlines,
    ) {
        $this->steps = iterator_to_array($steps, false);
    }

    public function erase(Entity $entity): ErasureOutcome
    {
        return DB::transaction(function () use ($entity): ErasureOutcome {
            DB::select("SELECT set_config('moashir.entity_erasure', 'on', true)");

            foreach ($this->steps as $step) {
                $step->erase($entity);
            }

            $pseudonym = self::PSEUDONYM_PREFIX.Str::upper(Str::random(12));
            $owner = User::withTrashed()->where('entity_id', $entity->id)->first();

            $this->deleteEntityRecords($entity, $owner);

            if ($owner instanceof User) {
                $this->ownerAnonymizer->anonymize($owner, $pseudonym);
            }

            $erasedAt = CarbonImmutable::now();
            $this->anonymizeEntity($entity, $pseudonym, $erasedAt);

            return new ErasureOutcome($entity->reference(), $pseudonym, $erasedAt, ErasureRetainedRecord::cases());
        });
    }

    private function deleteEntityRecords(Entity $entity, ?User $owner): void
    {
        EmailChangeRequest::query()
            ->where(static fn (Builder $query): Builder => $query
                ->where('entity_id', $entity->id)
                ->when($owner instanceof User, static fn (Builder $inner): Builder => $inner->orWhere('user_id', $owner?->id)))
            ->delete();
        EntityProfileChange::query()->where('entity_id', $entity->id)->delete();
        PrivacyRequest::query()->where('entity_id', $entity->id)->delete();
        $this->deadlines->forget($entity);
    }

    private function anonymizeEntity(Entity $entity, string $pseudonym, CarbonImmutable $erasedAt): void
    {
        $entity->forceFill([
            'name' => EntityName::pseudonym($pseudonym),
            'marketing_consent_at' => null,
            'marketing_unsubscribed_at' => null,
            'deactivation_reason' => null,
            'deletion_reason' => null,
            'verification_reminded_at' => null,
            'anonymized_at' => $erasedAt,
        ])->save();
    }
}
