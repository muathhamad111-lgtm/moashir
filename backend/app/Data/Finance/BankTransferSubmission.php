<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Models\Files\StoredFile;

final readonly class BankTransferSubmission
{
    public function __construct(
        public PaymentPurpose $purpose,
        public string $idempotencyKey,
        public string $bankAccountId,
        public PriceQuote $quote,
        public StoredFile $proof,
    ) {}
}
