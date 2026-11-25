<?php

declare(strict_types=1);

namespace App\Services\Entities\Timeline;

use App\Enums\Entities\EntityTimelineKind;
use App\Models\Entities\EntityProfileChange;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\EmailChangeRequest;
use Illuminate\Support\Collection;

/**
 * The records behind one page of timeline rows, each source read with a single query.
 */
final readonly class TimelineSources
{
    /**
     * @param  Collection<int, EntityProfileChange>  $fieldChanges
     * @param  Collection<int, EntityProfileChangeRequest>  $requests
     * @param  Collection<int, EmailChangeRequest>  $emails
     */
    public function __construct(public Collection $fieldChanges, public Collection $requests, public Collection $emails) {}

    /**
     * @param  list<TimelineRow>  $rows
     */
    public static function load(array $rows): self
    {
        $idsOf = static fn (EntityTimelineKind ...$kinds): array => array_values(array_unique(array_map(
            static fn (TimelineRow $row): int => $row->sourceId,
            array_filter($rows, static fn (TimelineRow $row): bool => in_array($row->kind, $kinds, true)),
        )));

        return new self(
            EntityProfileChange::query()->whereKey($idsOf(EntityTimelineKind::ProfileFieldChanged))->get()->keyBy('id'),
            EntityProfileChangeRequest::query()
                ->whereKey($idsOf(EntityTimelineKind::ProfileChangeRequested, EntityTimelineKind::ProfileChangeDecided))
                ->get()
                ->keyBy('id'),
            EmailChangeRequest::query()
                ->whereKey($idsOf(EntityTimelineKind::EmailChangeRequested, EntityTimelineKind::EmailChangeDecided))
                ->get()
                ->keyBy('id'),
        );
    }

    /**
     * The staff users who changed a field or decided a request on this page.
     *
     * @return list<int>
     */
    public function staffUserIds(): array
    {
        return array_values(array_filter([
            ...$this->fieldChanges->pluck('changed_by_user_id')->all(),
            ...$this->requests->pluck('reviewed_by_user_id')->all(),
            ...$this->emails->pluck('decided_by_user_id')->all(),
        ], is_int(...)));
    }
}
