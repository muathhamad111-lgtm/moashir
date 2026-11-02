<?php

declare(strict_types=1);

namespace App\Policies\Assessments;

use App\Models\Assessments\EvidenceBundle;
use App\Models\Identity\User;
use Illuminate\Auth\Access\Response;

final readonly class EvidenceBundlePolicy
{
    public function __construct(private AssessmentPolicy $assessments) {}

    public function download(User $actor, EvidenceBundle $bundle): Response
    {
        return $this->assessments->viewEvidenceBundle($actor, $bundle->assessment, $bundle);
    }
}
