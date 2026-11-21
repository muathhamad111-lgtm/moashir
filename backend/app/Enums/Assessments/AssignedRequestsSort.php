<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

/**
 * The columns an assessor can sort their assigned requests by.
 */
enum AssignedRequestsSort: string
{
    case DueAt = 'due_at';
    case AssignedAt = 'assigned_at';
    case Status = 'status';
}
