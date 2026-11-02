<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Integrations\PaymentGateway;
use App\Data\Integrations\CheckoutSession;
use App\Data\Integrations\PaymentCheckoutRequest;
use App\Data\Integrations\ProviderPayment;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\SecretValue;
use Carbon\CarbonImmutable;
use LogicException;

final class FakePaymentGateway implements PaymentGateway
{
    /**
     * @var array<string, ProviderPayment>
     */
    private array $payments = [];

    /**
     * @var list<string>
     */
    private array $cancelled = [];

    /**
     * @var list<PaymentCheckoutRequest>
     */
    private array $checkouts = [];

    /**
     * @var array<string, ProviderErrorKind>
     */
    private array $failures = [];

    private int $fetches = 0;

    public function createCheckout(PaymentCheckoutRequest $request): CheckoutSession
    {
        $this->failIfConfigured('create');
        $this->checkouts[] = $request;
        $number = count($this->checkouts);

        return new CheckoutSession(
            $request->mode,
            'fake-intent-'.$number,
            new SecretValue('fake-intent-secret-'.$number),
            'created',
            $request->amount,
            CarbonImmutable::now()->addHour(),
        );
    }

    public function fetchPayment(IntegrationMode $mode, string $providerPaymentId): ProviderPayment
    {
        $this->fetches++;
        $this->failIfConfigured('fetch');

        return $this->payments[$providerPaymentId] ?? throw new LogicException('The fake gateway has no payment '.$providerPaymentId.'.');
    }

    public function cancelCheckout(IntegrationMode $mode, string $intentId): void
    {
        $this->failIfConfigured('cancel');
        $this->cancelled[] = $intentId;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    public function recordPayment(string $id, string $status, Money $amount, array $metadata = [], ?string $responseCode = null, string $sourceType = 'creditcard', ?string $company = 'mada'): void
    {
        $this->payments[$id] = new ProviderPayment($id, $status, $amount, $sourceType, $company, '1111', $responseCode, null, $metadata);
    }

    public function fail(string $operation, ProviderErrorKind $kind): void
    {
        $this->failures[$operation] = $kind;
    }

    public function recover(string $operation): void
    {
        unset($this->failures[$operation]);
    }

    /**
     * @return list<string>
     */
    public function cancelledIntents(): array
    {
        return $this->cancelled;
    }

    /**
     * @return list<PaymentCheckoutRequest>
     */
    public function checkouts(): array
    {
        return $this->checkouts;
    }

    public function fetchCount(): int
    {
        return $this->fetches;
    }

    private function failIfConfigured(string $operation): void
    {
        $kind = $this->failures[$operation] ?? null;

        if ($kind instanceof ProviderErrorKind) {
            throw ProviderCallFailed::because(IntegrationProvider::Moyasar, $operation, $kind);
        }
    }
}
