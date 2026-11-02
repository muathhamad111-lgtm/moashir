<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum CommitteeAssignmentStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Voided = 'voided';

    public function acceptsWork(): bool
    {
        return $this === self::NotStarted || $this === self::InProgress;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
