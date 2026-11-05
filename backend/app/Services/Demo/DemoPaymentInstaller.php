<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoUserAccount;
use App\Data\Finance\OrderTerms;
use App\Data\Finance\PriceQuote;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Integrations\IntegrationMode;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\PaymentOrderOpener;
use App\Services\Finance\ServicePriceQuotes;
use Illuminate\Support\Str;

final readonly class DemoPaymentInstaller
{
    public function __construct(
        private PaymentOrderOpener $orders,
        private ServicePriceQuotes $quotes,
        private FinanceSettings $settings,
        private DemoTestPaymentSettlement $settlement,
    ) {}

    public function installPaid(DemoUserAccount $owner): Payment
    {
        $payment = $this->openAccreditedOrder($owner);
        $this->settlement->settle($payment);

        return $payment->refresh();
    }

    private function openAccreditedOrder(DemoUserAccount $owner): Payment
    {
        $entity = Entity::query()->findOrFail($owner->user->entity_id);

        return $this->orders->open(
            $owner->user,
            $entity,
            new OrderTerms(PaymentPurpose::Accredited, PaymentChannel::Electronic, $this->accreditedQuote(), IntegrationMode::Test, Str::uuid()->toString()),
        )->payment;
    }

    private function accreditedQuote(): PriceQuote
    {
        return $this->quotes->quoteOf(PaymentPurpose::Accredited, $this->settings->vatRate())
            ?? throw DemoDataRefused::missingPrerequisite('the accredited assessment price is not set');
    }
}
