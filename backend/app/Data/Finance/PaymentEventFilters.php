<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Integrations\IntegrationMode;

final readonly class PaymentEventFilters
{
    public function __construct(
        public ?PaymentEventSource $source = null,
        public ?PaymentEventResult $result = null,
        public ?IntegrationMode $mode = null,
        public ?string $search = null,
        public int $perPage = 15,
    ) {}
}
