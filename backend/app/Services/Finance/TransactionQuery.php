<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\TransactionFilters;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final readonly class TransactionQuery
{
    public function __construct(private PaymentPeriodFilter $period) {}

    /**
     * @return Builder<Payment>
     */
    public function filtered(TransactionFilters $filters): Builder
    {
        $query = Payment::query()
            ->where('mode', $filters->mode)
            ->when($filters->entityId !== null, static fn (Builder $inner): Builder => $inner->where('entity_id', $filters->entityId))
            ->when($filters->status instanceof PaymentStatus, static fn (Builder $query): Builder => $query->where('status', $filters->status))
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $query): Builder => $query->where('purpose', $filters->purpose))
            ->when($filters->method instanceof PaymentInstrument, static fn (Builder $query): Builder => $query->where('method', $filters->method))
            ->when($filters->channel instanceof PaymentChannel, static fn (Builder $query): Builder => $query->where('channel', $filters->channel))
            ->when($filters->search, static fn (Builder $query, SearchTerm $term): Builder => PaymentSearch::forStaff($query, $term));

        return $this->period->apply($query, $filters->from, $filters->to);
    }

    /**
     * @return Builder<Payment>
     */
    public function sorted(TransactionFilters $filters): Builder
    {
        return $this->filtered($filters)
            ->orderBy($filters->sort->column(), $filters->sort->direction())
            ->orderBy('id', $filters->sort->direction());
    }
}
