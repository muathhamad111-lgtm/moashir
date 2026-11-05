<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\BulkDecisionOutcome;
use App\Data\Finance\TransferRejection;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\BankTransferRejection;
use App\Services\Finance\BulkTransferDecider;
use App\Services\Finance\ReviewerReason;

final readonly class BulkRejectBankTransfers
{
    public function __construct(private BankTransferRejection $rejection, private BulkTransferDecider $decider, private ReviewerReason $reason) {}

    /**
     * @param  list<string>  $paymentIds
     */
    public function handle(User $actor, array $paymentIds, TransferRejection $rejection, ?string $reason): BulkDecisionOutcome
    {
        $this->reason->record($actor, $reason);
        $outcome = $this->decider->decideEach($paymentIds, fn (Payment $payment): Payment => $this->rejection->reject($actor, $payment, $rejection));

        if ($outcome->decided !== []) {
            $this->reason->alertIfSuperAdmin($actor);
        }

        return $outcome;
    }
}
