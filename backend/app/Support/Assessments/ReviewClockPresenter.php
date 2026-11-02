<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Models\Assessments\Assessment;
use Illuminate\Support\Facades\Date;

final class ReviewClockPresenter
{
    /**
     * @return array{submitted_at: string|null, due_at: string|null, is_overdue: bool, completed_at: string|null}
     */
    public static function of(Assessment $assessment): array
    {
        return [
            'submitted_at' => $assessment->submitted_at?->toIso8601String(),
            'due_at' => $assessment->due_at?->toIso8601String(),
            'is_overdue' => $assessment->isOverdue(Date::now()),
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];
    }
}
