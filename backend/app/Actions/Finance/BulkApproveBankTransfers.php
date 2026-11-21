<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\BulkDecisionOutcome;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\BankTransferApproval;
use App\Services\Finance\BulkTransferDecider;
use App\Services\Finance\ReviewerReason;

final readonly class BulkApproveBankTransfers
{
    public function __construct(private BankTransferApproval $approval, private BulkTransferDecider $decider, private ReviewerReason $reason) {}

    /**
     * @param  list<string>  $paymentIds
     */
    public function handle(User $actor, array $paymentIds, ?string $reason): BulkDecisionOutcome
    {
        $this->reason->record($actor, $reason);
        $outcome = $this->decider->decideEach($paymentIds, fn (Payment $payment): Payment => $this->approval->approve($actor, $payment));

        if ($outcome->decided !== []) {
            $this->reason->alertIfSuperAdmin($actor);
        }

        return $outcome;
    }
}
