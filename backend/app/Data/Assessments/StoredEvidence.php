<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\EvidenceFile;

final readonly class StoredEvidence
{
    public function __construct(public EvidenceFile $evidence, public BoundSubCriteria $subs) {}
}
