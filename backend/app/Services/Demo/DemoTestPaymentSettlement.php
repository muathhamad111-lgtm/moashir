<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Finance\PaymentInstrument;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentBinder;
use App\Services\Finance\PaymentRowLock;
use App\Services\Finance\PaymentSettlement;
use Illuminate\Support\Str;

final readonly class DemoTestPaymentSettlement
{
    private const string PROVIDER_ID_PREFIX = 'demo_';

    private const string TEST_CARD_LAST4 = '1111';

    public function __construct(
        private PaymentRowLock $rows,
        private PaymentBinder $binder,
        private PaymentSettlement $settlement,
    ) {}

    public function settle(Payment $payment): void
    {
        $locked = $this->rows->lock($payment);
        $this->binder->bind($locked, self::PROVIDER_ID_PREFIX.Str::uuid()->toString());
        $this->settlement->markPaid($locked, PaymentInstrument::CreditCard, self::TEST_CARD_LAST4);
    }
}
