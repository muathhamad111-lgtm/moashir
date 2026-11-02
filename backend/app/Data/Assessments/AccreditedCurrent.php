<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;

final readonly class AccreditedCurrent
{
    public function __construct(public ?Payment $awaitingPayment, public ?Assessment $active, public ?Assessment $latestClosed) {}
}
