<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Finance\OrderTerms;
use App\Data\Finance\PriceQuote;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Integrations\IntegrationMode;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\PaymentOrderOpener;
use App\Services\Finance\PaymentOrderWriter;
use App\Services\Finance\ServicePriceQuotes;
use Illuminate\Support\Str;

final readonly class DemoPaymentInstaller
{
    public function __construct(
        private PaymentOrderOpener $orders,
        private PaymentOrderWriter $writer,
        private ServicePriceQuotes $quotes,
        private FinanceSettings $settings,
        private DemoTestPaymentSettlement $settlement,
    ) {}

    public function installPaid(User $owner): Payment
    {
        return $this->settle($this->orders->open($owner, $this->entityOf($owner), $this->accreditedTerms())->payment);
    }

    public function writeOrder(User $owner): Payment
    {
        $entity = $this->entityOf($owner);

        if (Payment::query()->ownedBy($entity->id)->where('purpose', PaymentPurpose::Accredited)->open()->exists()) {
            throw DemoDataRefused::openPaymentOrder();
        }

        return $this->writer->write($owner, $entity, $this->accreditedTerms());
    }

    public function settle(Payment $payment): Payment
    {
        $this->settlement->settle($payment);

        return $payment->refresh();
    }

    private function entityOf(User $owner): Entity
    {
        return Entity::query()->findOrFail($owner->entity_id);
    }

    private function accreditedTerms(): OrderTerms
    {
        return new OrderTerms(PaymentPurpose::Accredited, PaymentChannel::Electronic, $this->accreditedQuote(), IntegrationMode::Test, Str::uuid()->toString());
    }

    private function accreditedQuote(): PriceQuote
    {
        return $this->quotes->quoteOf(PaymentPurpose::Accredited, $this->settings->vatRate())
            ?? throw DemoDataRefused::missingPrerequisite('the accredited assessment price is not set');
    }
}
