<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\EvidenceListing;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Assessments\EvidenceReader;

final readonly class ListEvidence
{
    public function __construct(private BoundSubCriteriaReader $subs, private EvidenceReader $evidence) {}

    public function handle(User $viewer, Assessment $assessment, ?int $subId): EvidenceListing
    {
        $subs = $this->subs->of($assessment->criteriaVersion);
        $subCode = $subId === null ? null : ($subs->withId($subId)->code ?? '');
        $files = $assessment->isOwnedBy($viewer->entity_id)
            ? $this->evidence->forOwner($assessment, $subCode)
            : $this->evidence->cleanOnly($assessment, $subCode);

        return new EvidenceListing(array_values($files), $subs);
    }
}
