<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum AssignmentStatus: string
{
    case Active = 'active';
    case Reassigned = 'reassigned';
    case Completed = 'completed';
    case Ended = 'ended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
