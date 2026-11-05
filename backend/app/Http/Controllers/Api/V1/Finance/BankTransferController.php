<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListBankTransfers;
use App\Actions\Finance\ShowBankTransfer;
use App\Http\Requests\Finance\IndexBankTransfersRequest;
use App\Http\Resources\Finance\BankTransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use Illuminate\Http\JsonResponse;

final class BankTransferController
{
    public function index(IndexBankTransfersRequest $request, ListBankTransfers $listTransfers): JsonResponse
    {
        return ApiResponse::paginated($listTransfers->handle($request->filters()), BankTransferResource::class, [
            'counts' => ['under_review' => $listTransfers->awaitingReviewCount()],
        ]);
    }

    public function show(Payment $bankTransfer, ShowBankTransfer $show): JsonResponse
    {
        return ApiResponse::resource(new BankTransferResource($show->handle($bankTransfer)));
    }
}
