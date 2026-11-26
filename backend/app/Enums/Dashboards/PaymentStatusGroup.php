<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Enums\Finance\PaymentStatus;

enum PaymentStatusGroup: string
{
    case Paid = 'paid';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public static function of(PaymentStatus $status): self
    {
        return match ($status) {
            PaymentStatus::Paid => self::Paid,
            PaymentStatus::Created, PaymentStatus::Pending => self::Pending,
            PaymentStatus::UnderReview => self::UnderReview,
            PaymentStatus::Failed, PaymentStatus::Rejected => self::Failed,
            PaymentStatus::Cancelled => self::Cancelled,
            PaymentStatus::Refunded => self::Refunded,
        };
    }
}
