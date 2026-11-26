<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ProfileChangeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public static function decidedFrom(int $approvedCount, int $rejectedCount): self
    {
        return match (true) {
            $rejectedCount === 0 => self::Approved,
            $approvedCount === 0 => self::Rejected,
            default => self::PartiallyApproved,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
