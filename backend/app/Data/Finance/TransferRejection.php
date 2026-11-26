<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\TransferRejectionReason;

final readonly class TransferRejection
{
    public function __construct(public TransferRejectionReason $reason, public ?string $note) {}
}
