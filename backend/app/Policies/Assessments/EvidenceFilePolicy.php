<?php

declare(strict_types=1);

namespace App\Policies\Assessments;

use App\Models\Assessments\EvidenceFile;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final readonly class EvidenceFilePolicy
{
    public function __construct(private AssessmentPolicy $assessments) {}

    public function download(User $actor, EvidenceFile $evidence): Response
    {
        return $evidence->isPresent() ? $this->assessments->downloadEvidence($actor, $evidence->assessment, $evidence) : Response::denyAsNotFound();
    }
}
