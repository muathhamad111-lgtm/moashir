<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\CheckoutOptions;
use App\Enums\Finance\PaymentChannel;
use App\Models\Entities\Entity;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use App\Services\Finance\PaymentChannelAvailability;

final readonly class ListCheckoutOptions
{
    public function __construct(private PaymentChannelAvailability $channels) {}

    public function handle(User $actor): CheckoutOptions
    {
        $available = $this->channels->available();
        $accounts = in_array(PaymentChannel::BankTransfer, $available, true)
            ? array_values(BankAccount::query()->selectable()->inDisplayOrder()->get()->all())
            : [];

        return new CheckoutOptions($available, $accounts, $this->unifiedNumberOf($actor));
    }

    private function unifiedNumberOf(User $actor): ?string
    {
        $entity = $actor->entity_id === null ? null : Entity::query()->find($actor->entity_id);

        return $entity?->unified_number;
    }
}
