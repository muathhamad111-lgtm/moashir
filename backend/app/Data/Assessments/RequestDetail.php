<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentAssignment;
use App\Models\Assessments\AssessmentCompletionRequest;

final readonly class RequestDetail
{
    /**
     * @param  list<AssessmentAssignment>  $assignments
     * @param  list<AssessmentCompletionRequest>  $completionRequests
     */
    public function __construct(
        public AssessmentRecord $record,
        public array $assignments,
        public array $completionRequests,
        public bool $revealsStaffIdentities,
        public bool $isPaid,
    ) {}
}
