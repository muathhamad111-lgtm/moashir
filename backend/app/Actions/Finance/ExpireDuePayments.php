<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentExpiry;
use Illuminate\Support\Facades\Date;

final readonly class ExpireDuePayments
{
    private const int CHUNK = 100;

    public function __construct(private PaymentExpiry $expiry) {}

    public function handle(): int
    {
        $handled = 0;

        Payment::query()
            ->where('channel', PaymentChannel::Electronic)
            ->open()
            ->where('expires_at', '<=', Date::now())
            ->chunkById(self::CHUNK, function ($payments) use (&$handled): void {
                foreach ($payments as $payment) {
                    $this->expiry->expire($payment);
                    $handled++;
                }
            });

        return $handled;
    }
}
