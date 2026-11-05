<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListTransactions;
use App\Http\Requests\Finance\IndexTransactionsRequest;
use App\Http\Resources\Finance\TransactionDetailResource;
use App\Http\Resources\Finance\TransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use Illuminate\Http\JsonResponse;

final class TransactionController
{
    public function index(IndexTransactionsRequest $request, ListTransactions $listTransactions): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($listTransactions->handle($filters), TransactionResource::class, [
            'stats' => $listTransactions->statsOf($filters)->toArray(),
        ]);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->loadMissing(['receipt', 'refund.payment', 'refund.recordedBy', 'refund.evidenceFile', 'entity.owner', 'bankTransfer.bankAccount', 'bankTransfer.proofFile', 'bankTransfer.reviewedBy']);

        return ApiResponse::resource(new TransactionDetailResource($payment));
    }
}
