<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum CancellationCause: string
{
    case Expired = 'expired';
    case CheckoutUnavailable = 'checkout_unavailable';
    case CancelledByEntity = 'cancelled_by_entity';
    case EntityErased = 'entity_erased';
}
