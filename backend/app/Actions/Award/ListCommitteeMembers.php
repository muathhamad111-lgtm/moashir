<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Award\CommitteeAssignmentStatus;
use App\Models\Award\AwardCycle;
use App\Models\Award\CycleCommitteeAssessor;
use Illuminate\Database\Eloquent\Builder;

final class ListCommitteeMembers
{
    /**
     * @return list<CycleCommitteeAssessor>
     */
    public function handle(AwardCycle $cycle): array
    {
        $counts = [];

        foreach (CommitteeAssignmentStatus::cases() as $status) {
            $counts['assignments as '.$status->value.'_count'] = static fn (Builder $rows): Builder => $rows->where('status', $status);
        }

        return array_values(CycleCommitteeAssessor::query()
            ->with(['assessor', 'replaces', 'replacedBy'])
            ->withCount($counts)
            ->where('award_cycle_id', $cycle->id)
            ->orderBy('id')
            ->get()
            ->all());
    }
}
