<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum AssessmentAccess: string
{
    case Owner = 'owner';
    case Assignee = 'assignee';
    case ReadOnly = 'read_only';
    case CycleMember = 'cycle_member';
    case None = 'none';

    public function canRead(): bool
    {
        return $this !== self::None;
    }

    public function revealsStaffIdentities(): bool
    {
        return $this === self::ReadOnly || $this === self::CycleMember;
    }
}
