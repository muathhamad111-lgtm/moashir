<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\BankTransferApproval;
use App\Services\Finance\ReviewerReason;

final readonly class ApproveBankTransfer
{
    public function __construct(private BankTransferApproval $approval, private ReviewerReason $reason) {}

    public function handle(User $actor, Payment $payment, ?string $reason): Payment
    {
        $this->reason->record($actor, $reason);
        $approved = $this->approval->approve($actor, $payment);
        $this->reason->alertIfSuperAdmin($actor);

        return $approved;
    }
}
