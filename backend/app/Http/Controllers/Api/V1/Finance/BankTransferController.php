<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListBankTransfers;
use App\Actions\Finance\ShowBankTransfer;
use App\Http\Requests\Finance\IndexBankTransfersRequest;
use App\Http\Resources\Finance\BankTransferDetailResource;
use App\Http\Resources\Finance\BankTransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class BankTransferController
{
    public function index(IndexBankTransfersRequest $request, ListBankTransfers $listTransfers): JsonResponse
    {
        return ApiResponse::paginated($listTransfers->handle($request->filters()), BankTransferResource::class, [
            'counts' => $listTransfers->counts()->toArray(),
        ]);
    }

    public function show(Payment $bankTransfer, #[CurrentUser] User $viewer, ShowBankTransfer $show): JsonResponse
    {
        return ApiResponse::resource(new BankTransferDetailResource($show->handle($bankTransfer, $viewer)));
    }
}
