<?php

declare(strict_types=1);

namespace App\Enums\Award;

/**
 * The columns a committee assessor can sort their committee assignments by.
 */
enum CommitteeAssignmentsSort: string
{
    case DueAt = 'due_at';
    case Status = 'status';
}
