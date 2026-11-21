<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class StaffBankTransferDetail
{
    public function __construct(public StaffBankTransfer $transfer, public ?LinkedRequest $linkedRequest) {}
}
