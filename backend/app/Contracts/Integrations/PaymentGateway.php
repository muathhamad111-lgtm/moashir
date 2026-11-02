<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Data\Integrations\CheckoutSession;
use App\Data\Integrations\PaymentCheckoutRequest;
use App\Data\Integrations\ProviderPayment;
use App\Enums\Integrations\IntegrationMode;

interface PaymentGateway
{
    public function createCheckout(PaymentCheckoutRequest $request): CheckoutSession;

    public function fetchPayment(IntegrationMode $mode, string $providerPaymentId): ProviderPayment;

    public function cancelCheckout(IntegrationMode $mode, string $intentId): void;
}
