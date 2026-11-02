<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\EvidenceFile;

final readonly class EvidenceListing
{
    /**
     * @param  list<EvidenceFile>  $evidence
     */
    public function __construct(public array $evidence, public BoundSubCriteria $subs) {}
}
