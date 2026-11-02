<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\MyPaymentFilters;
use App\Data\Finance\MyPaymentStats;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\FinanceStatsCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListMyPayments
{
    public function __construct(private FinanceStatsCalculator $stats) {}

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function handle(User $actor, MyPaymentFilters $filters): LengthAwarePaginator
    {
        return $this->filtered($actor, $filters)
            ->with('receipt')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }

    public function statsOf(User $actor, MyPaymentFilters $filters): MyPaymentStats
    {
        return $this->stats->ofEntity($this->filtered($actor, $filters)->live());
    }

    /**
     * @return Builder<Payment>
     */
    private function filtered(User $actor, MyPaymentFilters $filters): Builder
    {
        return Payment::query()
            ->ownedBy((int) $actor->entity_id)
            ->when($filters->status instanceof PaymentStatus, static fn (Builder $query): Builder => $query->where('status', $filters->status))
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $query): Builder => $query->where('purpose', $filters->purpose));
    }
}
