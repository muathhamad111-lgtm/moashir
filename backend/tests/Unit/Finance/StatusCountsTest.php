<?php

declare(strict_types=1);

use App\Data\Finance\ChannelStats;
use App\Data\Finance\StatusCounts;
use App\Support\ValueObjects\Money;

it('folds created and pending into awaiting payment and lists every ledger status', function (): void {
    $counts = StatusCounts::fromPaymentStatuses(['created' => 2, 'pending' => 1, 'paid' => 4, 'under_review' => 3]);

    expect($counts->toArray())->toBe([
        'paid' => 4,
        'under_review' => 3,
        'awaiting_payment' => 3,
        'rejected' => 0,
        'failed' => 0,
        'cancelled' => 0,
        'refunded' => 0,
    ])->and($counts->total())->toBe(10);
});

it('starts every status at zero when a channel has no operations', function (): void {
    $channel = new ChannelStats(StatusCounts::fromPaymentStatuses([]), Money::zero());

    expect($channel->toArray())->toBe([
        'count' => 0,
        'paid_total' => '0.00',
        'status_counts' => ['paid' => 0, 'under_review' => 0, 'awaiting_payment' => 0, 'rejected' => 0, 'failed' => 0, 'cancelled' => 0, 'refunded' => 0],
    ]);
});
