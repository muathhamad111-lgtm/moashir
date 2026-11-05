<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\BankTransferFilters;
use App\Data\Finance\StaffBankTransfer;
use App\Data\Finance\TransferQueueCounts;
use App\Models\Finance\Payment;
use App\Services\Finance\BankTransferQuery;
use App\Services\Finance\BankTransferQueueCounter;
use App\Services\Finance\DuplicateProofFinder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListBankTransfers
{
    public function __construct(
        private BankTransferQuery $query,
        private DuplicateProofFinder $duplicates,
        private BankTransferQueueCounter $counter,
    ) {}

    /**
     * @return LengthAwarePaginator<int, StaffBankTransfer>
     */
    public function handle(BankTransferFilters $filters): LengthAwarePaginator
    {
        $page = $this->query->sorted($filters)->with(BankTransferQuery::RELATIONS)->paginate($filters->perPage);
        $duplicated = $this->duplicates->duplicatedAmong($this->proofHashesOf($page->items()));

        return $page->through(static fn (Payment $payment): StaffBankTransfer => new StaffBankTransfer(
            $payment,
            in_array($payment->bankTransfer?->proofFile->sha256, $duplicated, true),
        ));
    }

    public function counts(): TransferQueueCounts
    {
        return $this->counter->count();
    }

    /**
     * @param  array<int, Payment>  $payments
     * @return list<string>
     */
    private function proofHashesOf(array $payments): array
    {
        $hashes = [];

        foreach ($payments as $payment) {
            $sha256 = $payment->bankTransfer?->proofFile->sha256;

            if ($sha256 !== null) {
                $hashes[] = $sha256;
            }
        }

        return $hashes;
    }
}
