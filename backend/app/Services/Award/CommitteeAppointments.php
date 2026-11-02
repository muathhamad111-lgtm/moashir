<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Contracts\Identity\CommitteeAppointmentsReader;
use App\Data\Identity\CommitteeAppointment;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

final class CommitteeAppointments implements CommitteeAppointmentsReader
{
    public function of(User $user): array
    {
        if ($user->entity_id !== null) {
            return [];
        }

        return array_values(CycleCommitteeAssessor::query()
            ->with('awardCycle')
            ->where('assessor_user_id', $user->id)
            ->active()
            ->whereHas('awardCycle', static fn (Builder $cycle): Builder => $cycle->where('status', '<>', AwardCycleStatus::Closed))
            ->orderBy('id')
            ->get()
            ->map(static fn (CycleCommitteeAssessor $member): CommitteeAppointment => new CommitteeAppointment(
                $member->id,
                $member->award_cycle_id,
                $member->awardCycle->name,
                $member->awardCycle->status->value,
                $member->status->value,
                $member->isSubstitute(),
                $member->awardCycle->committee_start,
                $member->awardCycle->committee_end,
            ))
            ->all());
    }
}
