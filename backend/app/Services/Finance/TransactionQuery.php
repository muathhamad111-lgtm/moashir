<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\TransactionFilters;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\TransactionType;
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
            ->when($filters->statuses !== [], static fn (Builder $query): Builder => $query->whereIn('status', self::valuesOf($filters->statuses)))
            ->when($filters->types !== [], static fn (Builder $query): Builder => $query->where(static fn (Builder $inner): Builder => self::ofTypes($inner, $filters->types)))
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

    /**
     * The types are OR-ed: refunded payments for `refund`, non-refunded payments of the channel otherwise.
     *
     * @param  Builder<Payment>  $query
     * @param  list<TransactionType>  $types
     * @return Builder<Payment>
     */
    private static function ofTypes(Builder $query, array $types): Builder
    {
        foreach ($types as $type) {
            $channel = $type->channel();

            $query->orWhere(static fn (Builder $inner): Builder => $channel instanceof PaymentChannel
                ? $inner->where('channel', $channel)->where('status', '!=', PaymentStatus::Refunded)
                : $inner->where('status', PaymentStatus::Refunded));
        }

        return $query;
    }

    /**
     * @param  list<PaymentStatus>  $statuses
     * @return list<string>
     */
    private static function valuesOf(array $statuses): array
    {
        return array_map(static fn (PaymentStatus $status): string => $status->value, $statuses);
    }
}
