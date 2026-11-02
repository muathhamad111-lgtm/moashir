<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;

final class AssessmentContextReader
{
    /**
     * @return list<Assessment>
     */
    public function for(User $viewer, Assessment $assessment): array
    {
        if (! $viewer->can(PermissionKey::AssessmentContextView->value)) {
            return [];
        }

        $context = [];

        foreach (AssessmentPath::cases() as $path) {
            $latest = Assessment::query()
                ->with('currentResult')
                ->ownedBy($assessment->entity_id)
                ->ofPath($path)
                ->whereKeyNot($assessment->id)
                ->whereNotNull('current_result_snapshot_id')
                ->whereNotIn('status', $path->openStateValues())
                ->latest('completed_at')
                ->first();

            if ($latest instanceof Assessment && $latest->exposesResult()) {
                $context[] = $latest;
            }
        }

        return $context;
    }
}
