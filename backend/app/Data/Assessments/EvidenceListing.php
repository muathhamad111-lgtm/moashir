<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\EvidenceFile;

final readonly class EvidenceListing
{
    /**
     * @param  list<EvidenceFile>  $evidence
     * @param  list<string>  $lateAttachmentKeys  required attachments that came after the submission (D-5), for reviewers
     */
    public function __construct(public array $evidence, public BoundSubCriteria $subs, public array $lateAttachmentKeys = []) {}
}
