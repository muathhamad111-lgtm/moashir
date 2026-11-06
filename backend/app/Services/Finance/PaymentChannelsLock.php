<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Services\Settings\SettingsLock;

final readonly class PaymentChannelsLock
{
    private const string SECTION = 'payment.channels';

    public function __construct(private SettingsLock $lock) {}

    public function acquire(): void
    {
        $this->lock->lock(self::SECTION);
    }
}
