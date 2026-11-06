<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Finance\PaymentChannel;

final readonly class PaymentChannelsUpdate
{
    /**
     * @param  list<PaymentChannel>  $channels
     */
    public function __construct(public array $channels, public ?string $reason) {}

    public function includes(PaymentChannel $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return array_map(static fn (PaymentChannel $channel): string => $channel->value, $this->channels);
    }
}
