<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Assessments\AdvancedRequestOpener;
use App\Services\Assessments\PaidRequestAlerts;
use App\Services\Award\AwardEntryLedger;
use App\Services\Finance\PaymentAlerts;
use Illuminate\Support\Facades\DB;

final readonly class OpenPaidAwardDraft
{
    public function __construct(
        private AwardEntryLedger $ledger,
        private AdvancedRequestOpener $opener,
        private PaidRequestAlerts $alerts,
        private PaymentAlerts $paymentAlerts,
    ) {}

    public function handle(int $paymentId): ?Assessment
    {
        return DB::transaction(function () use ($paymentId): ?Assessment {
            $payment = Payment::query()->with(['entity', 'awardCycle.regions'])->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->purpose !== PaymentPurpose::Award || $payment->status !== PaymentStatus::Paid || ! $payment->awardCycle instanceof AwardCycle) {
                return null;
            }

            return Assessment::query()->where('payment_id', $payment->id)->first() ?? $this->openFor($payment, $payment->awardCycle);
        });
    }

    private function openFor(Payment $payment, AwardCycle $cycle): ?Assessment
    {
        Entity::withTrashed()->whereKey($payment->entity_id)->lockForUpdate()->first();
        $existing = $this->ledger->requestIn($payment->entity_id, $cycle->id);

        if ($existing instanceof Assessment) {
            $this->alerts->duplicate($payment, $existing->id);

            return null;
        }

        $assessment = $this->opener->openPaidDraft($payment->entity, $cycle, $payment);

        if ($payment->wasPaidAfterWindowOf($cycle)) {
            $this->paymentAlerts->refundRequired($payment);
        }

        return $assessment;
    }
}
