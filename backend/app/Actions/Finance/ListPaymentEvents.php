<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\PaymentEventFilters;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Finance\PaymentEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPaymentEvents
{
    /**
     * @return LengthAwarePaginator<int, PaymentEvent>
     */
    public function handle(PaymentEventFilters $filters): LengthAwarePaginator
    {
        $search = $filters->search === null ? null : '%'.addcslashes(mb_strtolower($filters->search), '\\%_').'%';

        return PaymentEvent::query()
            ->with('payment:id,public_id,reference')
            ->when($filters->source instanceof PaymentEventSource, static fn (Builder $query): Builder => $query->where('source', $filters->source))
            ->when($filters->result instanceof PaymentEventResult, static fn (Builder $query): Builder => $query->where('processing_result', $filters->result))
            ->when($filters->mode instanceof IntegrationMode, static fn (Builder $query): Builder => $query->where('mode', $filters->mode))
            ->when($search !== null, static fn (Builder $query): Builder => $query->where(static fn (Builder $inner): Builder => $inner
                ->whereRaw('lower(coalesce(provider_payment_id, \'\')) like ?', [$search])
                ->orWhereHas('payment', static fn (Builder $payment): Builder => $payment->whereRaw('lower(reference) like ?', [$search]))))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }
}
