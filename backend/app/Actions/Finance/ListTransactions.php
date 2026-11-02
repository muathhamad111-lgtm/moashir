<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\FinanceStats;
use App\Data\Finance\TransactionFilters;
use App\Models\Finance\Payment;
use App\Services\Finance\FinanceStatsCalculator;
use App\Services\Finance\TransactionQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListTransactions
{
    public const array RELATIONS = ['receipt', 'refund', 'entity.owner'];

    public function __construct(private TransactionQuery $query, private FinanceStatsCalculator $stats) {}

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function handle(TransactionFilters $filters): LengthAwarePaginator
    {
        return $this->query->sorted($filters)->with(self::RELATIONS)->paginate($filters->perPage);
    }

    public function statsOf(TransactionFilters $filters): FinanceStats
    {
        return $this->stats->overall($this->query->filtered($filters->forLiveMode()));
    }
}
