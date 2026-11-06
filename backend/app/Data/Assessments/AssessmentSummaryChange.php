<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class AssessmentSummaryChange
{
    public function __construct(public int $version, public bool $cleared) {}
}
