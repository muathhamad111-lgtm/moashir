<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListTransferAccounts;
use App\Http\Requests\Finance\IndexTransferAccountsRequest;
use App\Http\Resources\Finance\TransferAccountResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class BankTransferAccountController
{
    public function __invoke(IndexTransferAccountsRequest $request, ListTransferAccounts $listAccounts): JsonResponse
    {
        return ApiResponse::paginated($listAccounts->handle($request->perPage()), TransferAccountResource::class);
    }
}
