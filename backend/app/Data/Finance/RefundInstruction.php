<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Support\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

final readonly class RefundInstruction
{
    public function __construct(
        public Money $amount,
        public CarbonImmutable $refundedOn,
        public string $reason,
        public string $transferReference,
        public UploadedFile $evidence,
    ) {}
}
