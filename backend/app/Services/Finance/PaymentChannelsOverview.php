<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\PaymentChannelsView;

final readonly class PaymentChannelsOverview
{
    public function __construct(private PaymentChannelAvailability $availability, private TransferReviewers $reviewers) {}

    public function read(): PaymentChannelsView
    {
        return new PaymentChannelsView(
            $this->availability->enabled(),
            $this->availability->available(),
            $this->availability->isGatewayReady(),
            $this->availability->selectableBankAccountsCount(),
            $this->reviewers->activeCount(),
        );
    }
}
