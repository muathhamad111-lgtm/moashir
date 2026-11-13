<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Enums\Finance\PaymentChannel;

final readonly class PaymentChannelsView
{
    /**
     * @param  list<PaymentChannel>  $enabled
     * @param  list<PaymentChannel>  $available
     */
    public function __construct(
        public array $enabled,
        public array $available,
        public bool $isGatewayReady,
        public int $activeBankAccountsCount,
        public int $reviewersCount,
    ) {}
}
