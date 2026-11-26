<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\OpenedOrder;
use App\Data\Finance\OrderTerms;
use App\Enums\Finance\PaymentChannel;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;

final readonly class PaymentOrderOpener
{
    public function __construct(private PurchaseEligibility $eligibility, private PaymentOrderWriter $writer) {}

    public function open(User $actor, Entity $entity, OrderTerms $terms): OpenedOrder
    {
        Entity::query()->whereKey($entity->id)->lockForUpdate()->first();

        $existing = Payment::query()->ownedBy($entity->id)->forService($terms->purpose, $terms->cycle?->id)->open()->first();

        if ($existing instanceof Payment) {
            return $this->reuse($existing, $terms);
        }

        $this->eligibility->assertMayPurchase($entity, $terms);

        return new OpenedOrder($this->writer->write($actor, $entity, $terms), false);
    }

    private function reuse(Payment $existing, OrderTerms $terms): OpenedOrder
    {
        if ($terms->channel !== PaymentChannel::Electronic || $existing->channel !== PaymentChannel::Electronic) {
            throw PaymentRejected::openOrderExists($existing);
        }

        return new OpenedOrder($existing, true);
    }
}
