<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentChannel;
use App\Models\Finance\BankAccount;

final readonly class CheckoutOptions
{
    /**
     * @param  list<PaymentChannel>  $channels
     * @param  list<BankAccount>  $bankAccounts
     */
    public function __construct(public array $channels, public array $bankAccounts, public ?string $unifiedNumber, public ?PathPricing $pricing = null) {}
}
