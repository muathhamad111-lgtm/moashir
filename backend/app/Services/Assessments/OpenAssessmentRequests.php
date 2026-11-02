<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Entities\OpenRequestSource;
use App\Data\Entities\OpenEntityRequest;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;

final class OpenAssessmentRequests implements OpenRequestSource
{
    public function openRequestsOf(Entity $entity): array
    {
        return array_values(Assessment::query()
            ->ownedBy($entity->id)
            ->open()
            ->orderBy('id')
            ->get()
            ->map(static fn (Assessment $assessment): OpenEntityRequest => new OpenEntityRequest(
                $assessment->id,
                $assessment->request_number,
                $assessment->path->requestPath(),
                (string) $assessment->status->value,
                $assessment->award_cycle_id,
            ))
            ->all());
    }
}
