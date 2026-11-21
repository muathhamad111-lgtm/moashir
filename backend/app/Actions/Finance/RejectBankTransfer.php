<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\TransferRejection;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\BankTransferRejection;
use App\Services\Finance\ReviewerReason;

final readonly class RejectBankTransfer
{
    public function __construct(private BankTransferRejection $rejection, private ReviewerReason $reason) {}

    public function handle(User $actor, Payment $payment, TransferRejection $rejection, ?string $reason): Payment
    {
        $this->reason->record($actor, $reason);
        $rejected = $this->rejection->reject($actor, $payment, $rejection);
        $this->reason->alertIfSuperAdmin($actor);

        return $rejected;
    }
}
