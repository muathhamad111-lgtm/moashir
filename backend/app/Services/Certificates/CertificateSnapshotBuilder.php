<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Enums\Certificates\CertificateLevel;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Support\ValueObjects\LevelThresholds;

final class CertificateSnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Assessment $assessment, AssessmentResultSnapshot $result, LevelThresholds $thresholds, ?CertificateLevel $level): array
    {
        return [
            'entity_name' => $assessment->entity->name->toArray(),
            'entity_reference' => $assessment->entity->reference(),
            'request_number' => $assessment->request_number,
            'percent' => $result->percent->toDisplay(),
            'level' => $level?->value,
            'level_thresholds' => $thresholds->toArray(),
            'result_version' => $result->version,
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];
    }
}
