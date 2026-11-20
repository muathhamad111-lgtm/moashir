<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;
use Carbon\CarbonImmutable;

/**
 * One row of the request queue: the request, the entity's progress while it still prepares it, and the end of the
 * current period (the deadline a paid-draft reminder refers to).
 */
final readonly class RequestQueueRow
{
    public function __construct(
        public Assessment $assessment,
        public ?SubmissionProgress $progress = null,
        public ?CarbonImmutable $currentPeriodEndsOn = null,
    ) {}
}
