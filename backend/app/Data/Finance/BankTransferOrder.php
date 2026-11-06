<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentPurpose;
use Illuminate\Http\UploadedFile;

final readonly class BankTransferOrder
{
    public function __construct(
        public PaymentPurpose $purpose,
        public string $idempotencyKey,
        public string $bankAccountId,
        public UploadedFile $proof,
    ) {}
}
