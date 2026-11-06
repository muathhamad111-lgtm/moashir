<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Exceptions\Finance\BankAccountRejected;
use App\Models\Finance\BankAccount;
use App\Support\Finance\BankTransferPresenter;

final readonly class BankAccountRetirementGuard
{
    public function __construct(private PendingTransfersOnAccount $pendingTransfers, private PaymentChannelAvailability $availability) {}

    public function assertMayRetire(BankAccount $locked): void
    {
        $pending = $this->pendingTransfers->of($locked);

        if ($pending->isNotEmpty()) {
            throw BankAccountRejected::pendingTransfers(array_values($pending->map(BankTransferPresenter::pending(...))->all()));
        }

        if ($this->isLastSelectable($locked) && $this->availability->isEnabled(PaymentChannel::BankTransfer)) {
            throw BankAccountRejected::lastActiveAccount();
        }
    }

    private function isLastSelectable(BankAccount $locked): bool
    {
        return $locked->isSelectable() && $this->availability->selectableBankAccountsCount() === 1;
    }
}
