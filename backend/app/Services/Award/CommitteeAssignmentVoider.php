<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CommitteeVoidReason;
use App\Models\Award\CommitteeAssignment;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final class CommitteeAssignmentVoider
{
    /**
     * @param  Builder<CommitteeAssignment>  $assignments
     * @return list<int>
     */
    public function void(Builder $assignments, CommitteeVoidReason $reason): array
    {
        $live = $assignments->live();
        $ids = array_values((clone $live)->pluck('assessment_id')->map(static fn (mixed $id): int => ScalarValue::int($id))->unique()->values()->all());

        $live->update([
            'status' => CommitteeAssignmentStatus::Voided,
            'voided_at' => Date::now(),
            'void_reason' => $reason,
            'updated_at' => Date::now(),
        ]);

        return $ids;
    }
}
