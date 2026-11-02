<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\RefundFilters;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\Refund;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListRefunds
{
    public const array RELATIONS = ['payment', 'recordedBy', 'evidenceFile'];

    /**
     * @return LengthAwarePaginator<int, Refund>
     */
    public function handle(RefundFilters $filters): LengthAwarePaginator
    {
        return Refund::query()
            ->with(self::RELATIONS)
            ->when($filters->from instanceof CarbonImmutable, static fn (Builder $query): Builder => $query->where('refunded_on', '>=', $filters->from?->toDateString()))
            ->when($filters->to instanceof CarbonImmutable, static fn (Builder $query): Builder => $query->where('refunded_on', '<=', $filters->to?->toDateString()))
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $query): Builder => $query->whereHas('payment', static fn (Builder $payment): Builder => $payment->where('purpose', $filters->purpose)))
            ->orderByDesc('refunded_on')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }
}
