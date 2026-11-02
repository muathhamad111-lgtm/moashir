<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Models\Award\FilteringDecision;

final class FilteringDecisionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(FilteringDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'outcome' => $decision->outcome->value,
            'is_override' => $decision->is_override,
            'initial_percent' => $decision->initial_percent->toDisplay(),
            'threshold_percent' => $decision->threshold_percent->toDisplay(),
            'reason' => $decision->reason,
            'note' => $decision->note,
            'decided_by' => ['id' => $decision->decided_by_user_id, 'name' => $decision->decidedBy->isSuperAdmin() ? null : $decision->decidedBy->name],
            'decided_at' => $decision->created_at->toIso8601String(),
        ];
    }
}
