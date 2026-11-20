<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\EvidenceLevel;

/**
 * The main criterion or sub-criterion an upload names, before it is checked against the request's version.
 */
final readonly class EvidenceNodeRef
{
    public function __construct(public EvidenceLevel $level, public int $id) {}
}
