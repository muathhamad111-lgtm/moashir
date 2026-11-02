<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum EmailChangeStatus: string
{
    case AwaitingOtp = 'awaiting_otp';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
