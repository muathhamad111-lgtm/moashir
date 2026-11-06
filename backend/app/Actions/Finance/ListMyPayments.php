<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Contracts\Finance\OpenedRequestLookup;
use App\Data\Finance\MyPaymentFilters;
use App\Data\Finance\MyPaymentStats;
use App\Data\Finance\OwnPayment;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\FinanceStatsCalculator;
use App\Support\StatusCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListMyPayments
{
    public function __construct(private FinanceStatsCalculator $stats, private OpenedRequestLookup $openedRequests) {}

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
        return $this->ownedForPurpose($actor, $filters)
            ->when($filters->statuses !== [], static fn (Builder $query): Builder => $query->whereIn('status', $filters->statuses));
    }

    /**
     * @return Builder<Payment>
     */
    private function ownedForPurpose(User $actor, MyPaymentFilters $filters): Builder
    {
        return Payment::query()
            ->ownedBy((int) $actor->entity_id)
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $query): Builder => $query->where('purpose', $filters->purpose));
    }
}
