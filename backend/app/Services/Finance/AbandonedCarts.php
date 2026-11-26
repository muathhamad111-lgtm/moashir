<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\CheckoutIntent;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AbandonedCarts
{
    public function __construct(private FinanceSettings $settings) {}

    /**
     * @return Builder<CheckoutIntent>
     */
    public function query(): Builder
    {
        return CheckoutIntent::query()
            ->whereNull('converted_payment_id')
            ->where('last_seen_at', '<=', Date::now()->subHours($this->settings->abandonedCartAfterHours()))
            ->whereHas('entity', static fn (Builder $entity): Builder => $entity->notErased())
            ->whereNotExists(static fn (QueryBuilder $transfers): QueryBuilder => $transfers
                ->select(DB::raw(1))
                ->from('payments')
                ->whereColumn('payments.entity_id', 'checkout_intents.entity_id')
                ->whereColumn('payments.purpose', 'checkout_intents.purpose')
                ->where('payments.channel', PaymentChannel::BankTransfer->value)
                ->where('payments.status', PaymentStatus::UnderReview->value));
    }
}
