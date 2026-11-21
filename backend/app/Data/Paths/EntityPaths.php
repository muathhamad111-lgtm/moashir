<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Models\Finance\Payment;

final readonly class EntityPaths
{
    public function __construct(
        public SelfPathSummary $self,
        public AccreditedPathSummary $accredited,
        public AwardPathSummary $award,
        public ?Payment $paymentToSettle,
    ) {}
}
