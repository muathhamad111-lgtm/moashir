<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Support\ValueObjects\Iban;
use App\Support\ValueObjects\LocalizedText;

final readonly class NewBankAccount
{
    public function __construct(
        public LocalizedText $bankName,
        public LocalizedText $accountHolderName,
        public Iban $iban,
        public ?string $accountNumber,
        public ?LocalizedText $payerNote,
    ) {}
}
