<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;

final class AwardEntryLedger
{
    public function requestIn(int $entityId, int $cycleId): ?Assessment
    {
        return Assessment::query()->ownedBy($entityId)->inCycle($cycleId)->whereNot('status', AdvancedAssessmentStatus::RejectedConditions)->first();
    }

    public function openOrder(int $entityId, int $cycleId): ?Payment
    {
        return Payment::query()->ownedBy($entityId)->forService(PaymentPurpose::Award, $cycleId)->open()->latest('id')->first();
    }

    public function hasPaidOrderAwaitingDraft(int $entityId, int $cycleId): bool
    {
        return Payment::query()
            ->ownedBy($entityId)
            ->forService(PaymentPurpose::Award, $cycleId)
            ->where('status', PaymentStatus::Paid)
            ->whereNotIn('id', Assessment::query()->whereNotNull('payment_id')->select('payment_id'))
            ->exists();
    }
}
