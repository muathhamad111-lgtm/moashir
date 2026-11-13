<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Finance\OpenedRequestLookup;
use App\Data\Finance\MyPaymentFilters;
use App\Data\Finance\MyPaymentStats;
use App\Data\Finance\OwnPayment;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\EntityPaymentVisibility;
use App\Services\Finance\FinanceStatsCalculator;
use App\Services\Finance\PaymentPeriodFilter;
use App\Services\Finance\PaymentSearch;
use App\Support\StatusCounts;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListMyPayments
{
    public function __construct(
        private FinanceStatsCalculator $stats,
        private OpenedRequestLookup $openedRequests,
        private EntityPaymentVisibility $visibility,
        private PaymentPeriodFilter $period,
    ) {}

    /**
     * @return LengthAwarePaginator<int, OwnPayment>
     */
    public function handle(User $actor, MyPaymentFilters $filters): LengthAwarePaginator
    {
        $page = $this->filtered($actor, $filters)
            ->with(ShowMyPayment::RELATIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
        $openedRequests = $this->openedRequests->forPayments(array_values(array_map(static fn (Payment $payment): int => $payment->id, $page->items())));

        return $page->through(static fn (Payment $payment): OwnPayment => new OwnPayment($payment, $openedRequests[$payment->id] ?? null));
    }

    public function statsOf(User $actor, MyPaymentFilters $filters): MyPaymentStats
    {
        return $this->stats->ofEntity($this->filtered($actor, $filters)->live());
    }

    /**
     * @return array<string, int>
     */
    public function statusCountsOf(User $actor, MyPaymentFilters $filters): array
    {
        return StatusCounts::of($this->ownedForPurpose($actor, $filters), PaymentStatus::cases());
    }

    /**
     * @return Builder<Payment>
     */
    private function filtered(User $actor, MyPaymentFilters $filters): Builder
    {
        $query = $this->ownedForPurpose($actor, $filters)
            ->when($filters->statuses !== [], static fn (Builder $query): Builder => $query->whereIn('status', $filters->statuses))
            ->when($filters->method instanceof PaymentInstrument, static fn (Builder $query): Builder => $query->where('method', $filters->method))
            ->when($filters->channel instanceof PaymentChannel, static fn (Builder $query): Builder => $query->where('channel', $filters->channel))
            ->when($filters->search, static fn (Builder $query, SearchTerm $term): Builder => PaymentSearch::forEntity($query, $term));

        return $this->period->apply($query, $filters->from, $filters->to);
    }

    /**
     * @return Builder<Payment>
     */
    private function ownedForPurpose(User $actor, MyPaymentFilters $filters): Builder
    {
        return Payment::query()
            ->ownedBy((int) $actor->entity_id)
            ->when($this->visibility->hidesTestPayments(), static fn (Builder $query): Builder => $query->live())
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $query): Builder => $query->where('purpose', $filters->purpose));
    }
}
