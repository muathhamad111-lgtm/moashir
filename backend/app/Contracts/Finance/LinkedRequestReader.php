<?php

declare(strict_types=1);

namespace App\Contracts\Finance;

use App\Data\Finance\LinkedRequest;
use App\Models\Finance\Payment;
use App\Models\Identity\User;

interface LinkedRequestReader
{
    public function forPayment(Payment $payment, User $viewer): ?LinkedRequest;
}
