<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Data\Insights\ResultCandidate;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Identity\RequestPathScope;

final readonly class VisibleAccreditedResults
{
    public function __construct(private RequestPathScope $paths, private AccreditedResultsReader $results) {}

    public function coversAccredited(User $viewer): bool
    {
        return $this->paths->covers($viewer, AssessmentPath::Accredited);
    }

    /**
     * @return list<ResultCandidate>
     */
    public function of(User $viewer, Entity $entity): array
    {
        return $this->coversAccredited($viewer) ? $this->results->forEntity($entity->id) : [];
    }
}
