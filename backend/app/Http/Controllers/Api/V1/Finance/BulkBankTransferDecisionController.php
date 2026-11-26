<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\BulkApproveBankTransfers;
use App\Actions\Finance\BulkRejectBankTransfers;
use App\Http\Requests\Finance\BulkApproveBankTransfersRequest;
use App\Http\Requests\Finance\BulkRejectBankTransfersRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class BulkBankTransferDecisionController
{
    public function approve(BulkApproveBankTransfersRequest $request, #[CurrentUser] User $actor, BulkApproveBankTransfers $approve): JsonResponse
    {
        return ApiResponse::data($approve->handle($actor, $request->paymentIds(), $request->reason())->toArray());
    }

    public function reject(BulkRejectBankTransfersRequest $request, #[CurrentUser] User $actor, BulkRejectBankTransfers $reject): JsonResponse
    {
        return ApiResponse::data($reject->handle($actor, $request->paymentIds(), $request->rejection(), $request->reason())->toArray());
    }
}
