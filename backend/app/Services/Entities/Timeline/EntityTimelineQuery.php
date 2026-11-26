<?php

declare(strict_types=1);

namespace App\Services\Entities\Timeline;

use App\Enums\Entities\EntityTimelineKind;
use App\Enums\Entities\EntityTimelineScope;
use App\Enums\Identity\EmailChangeStatus;
use App\Models\Entities\Entity;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds one newest-first feed of (kind, source_id, occurred_at) rows for an entity, so its account events,
 * profile changes and e-mail changes page together.
 */
final class EntityTimelineQuery
{
    private const array EMAIL_REQUESTED_HIDDEN = [EmailChangeStatus::AwaitingOtp, EmailChangeStatus::Expired];

    private const array EMAIL_DECIDED = [EmailChangeStatus::Approved, EmailChangeStatus::Rejected, EmailChangeStatus::Applied];

    public function for(Entity $entity, EntityTimelineScope $scope): Builder
    {
        // Every entity has a creation date, so its «created» row starts the feed.
        $feed = $this->rows('entities', EntityTimelineKind::Created, 'created_at')->where('id', $entity->id);
        $parts = $scope === EntityTimelineScope::Full
            ? [...$this->lifecycleOf($entity), ...$this->changesOf($entity)]
            : $this->lifecycleOf($entity);

        foreach ($parts as $part) {
            $feed->unionAll($part);
        }

        return DB::query()->fromSub($feed, 'timeline')
            ->orderByDesc('occurred_at')
            ->orderByDesc('sequence')
            ->orderByDesc('source_id');
    }

    /**
     * @return list<Builder>
     */
    private function lifecycleOf(Entity $entity): array
    {
        $parts = [];

        foreach (EntityTimelineKind::lifecycleColumns() as $column => $kind) {
            if ($kind === EntityTimelineKind::Created) {
                continue;
            }

            $parts[] = $this->rows('entities', $kind, $column)->where('id', $entity->id)->whereNotNull($column);
        }

        return $parts;
    }

    /**
     * @return list<Builder>
     */
    private function changesOf(Entity $entity): array
    {
        return [
            $this->rows('entity_profile_changes', EntityTimelineKind::ProfileFieldChanged, 'created_at')
                ->where('entity_id', $entity->id),
            $this->rows('entity_profile_change_requests', EntityTimelineKind::ProfileChangeRequested, 'created_at')
                ->where('entity_id', $entity->id),
            $this->rows('entity_profile_change_requests', EntityTimelineKind::ProfileChangeDecided, 'decided_at')
                ->where('entity_id', $entity->id)
                ->whereNotNull('decided_at'),
            $this->rows('email_change_requests', EntityTimelineKind::EmailChangeRequested, 'created_at')
                ->where('entity_id', $entity->id)
                ->whereNotIn('status', $this->values(self::EMAIL_REQUESTED_HIDDEN)),
            $this->rows('email_change_requests', EntityTimelineKind::EmailChangeDecided, DB::raw('COALESCE(applied_at, updated_at) AS occurred_at'))
                ->where('entity_id', $entity->id)
                ->whereIn('status', $this->values(self::EMAIL_DECIDED)),
        ];
    }

    /**
     * @param  string|Expression  $occurredAt  a date column, or an expression already aliased «occurred_at»
     */
    private function rows(string $table, EntityTimelineKind $kind, string|Expression $occurredAt): Builder
    {
        return DB::table($table)
            ->selectRaw('CAST(? AS varchar) AS kind, CAST(? AS integer) AS sequence', [$kind->value, $kind->sequence()])
            ->addSelect('id as source_id', is_string($occurredAt) ? "{$occurredAt} as occurred_at" : $occurredAt);
    }

    /**
     * @param  list<EmailChangeStatus>  $statuses
     * @return list<string>
     */
    private function values(array $statuses): array
    {
        return array_map(static fn (EmailChangeStatus $status): string => $status->value, $statuses);
    }
}
