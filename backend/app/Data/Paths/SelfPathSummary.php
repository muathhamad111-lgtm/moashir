<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;

final readonly class SelfPathSummary
{
    public function __construct(
        public PathPricing $pricing,
        public ?Assessment $openDraft,
        public ?DraftProgress $draftProgress,
        public ?Assessment $lastResult,
        public ?Payment $awaitingPayment,
        public bool $isListed,
    ) {}

    public function hasPaidDraft(): bool
    {
        return $this->openDraft?->payment_id !== null;
    }
}
