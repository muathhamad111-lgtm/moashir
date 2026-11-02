<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Entities\FreezableDeadlineSource;
use App\Data\Entities\FreezableDeadline;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use Illuminate\Support\Carbon;

final class AssessmentDeadlines implements FreezableDeadlineSource
{
    private const string DUE_AT = 'due_at';

    public function runningDeadlinesOf(Entity $entity): array
    {
        $deadlines = [];
        $running = Assessment::query()->ownedBy($entity->id)->open()->whereNotNull(self::DUE_AT)->orderBy('id')->get();

        foreach ($running as $assessment) {
            if ($assessment->due_at instanceof Carbon) {
                $deadlines[] = new FreezableDeadline($assessment, self::DUE_AT, $assessment->due_at);
            }
        }

        return $deadlines;
    }
}
