<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignedRequestsTab;
use App\Support\ListOrder;

/**
 * One page of an assessor's assigned requests: the path, an optional status group and the order.
 */
final readonly class AssignedRequestsQuery
{
    public function __construct(
        public AssessmentPath $path,
        public ?AssignedRequestsTab $tab,
        public ListOrder $order,
        public int $perPage,
    ) {}
}
