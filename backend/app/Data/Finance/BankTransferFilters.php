<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\BankTransferSort;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Support\ValueObjects\SearchTerm;

final readonly class BankTransferFilters
{
    public function __construct(
        public ?SearchTerm $search = null,
        public ?PaymentStatus $status = PaymentStatus::UnderReview,
        public ?PaymentPurpose $purpose = null,
        public ?string $bankAccountId = null,
        public ?IntegrationMode $mode = null,
        public bool $frozenEntities = false,
        public BankTransferSort $sort = BankTransferSort::OldestFirst,
        public int $perPage = 15,
    ) {}
}
