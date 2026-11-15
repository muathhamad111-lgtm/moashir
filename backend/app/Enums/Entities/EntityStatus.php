<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum EntityStatus: string
{
    case PendingVerification = 'pending_verification';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Rejected = 'rejected';
}
