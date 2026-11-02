<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\VerifiedPaymentEvent;

interface PaymentWebhookVerifier
{
    public function verify(string $payload): VerifiedPaymentEvent;
}
