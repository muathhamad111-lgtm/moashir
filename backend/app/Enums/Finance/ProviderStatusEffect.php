<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum ProviderStatusEffect: string
{
    case AwaitPayment = 'await_payment';
    case Settle = 'settle';
    case Fail = 'fail';
    case ReportRefund = 'report_refund';
    case Unexpected = 'unexpected';
}
