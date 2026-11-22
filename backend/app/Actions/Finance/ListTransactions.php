<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\FinanceBreakdown;
use App\Data\Finance\FinanceStats;
use App\Data\Finance\TransactionFilters;
use App\Models\Finance\Payment;
use App\Services\Finance\FinanceBreakdownCalculator;
use App\Services\Finance\FinanceStatsCalculator;
use App\Services\Finance\TransactionQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListTransactions
{
    // The list no longer shows the owner e-mail, but the CSV export (finance staff only) still does.
    public const array RELATIONS = ['receipt', 'refund', 'entity.owner', 'bankTransfer.bankAccount', 'awardCycle'];

    private const array REFUND_RELATIONS = ['refund.recordedBy:id,name', 'refund.evidenceFile:id'];

    public function __construct(
        private TransactionQuery $query,
        private FinanceStatsCalculator $stats,
        private FinanceBreakdownCalculator $breakdown,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function handle(TransactionFilters $filters): LengthAwarePaginator
    {
        return $this->query->sorted($filters)->with([...self::RELATIONS, ...self::REFUND_RELATIONS])->paginate($filters->perPage);
    }

    public function statsOf(TransactionFilters $filters): FinanceStats
    {
        return $this->stats->overall($this->query->filtered($filters));
    }

    public function breakdownOf(TransactionFilters $filters): FinanceBreakdown
    {
        return $this->breakdown->of($this->query->filtered($filters));
    }
}
