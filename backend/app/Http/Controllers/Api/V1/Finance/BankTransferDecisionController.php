<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ApproveBankTransfer;
use App\Actions\Finance\RejectBankTransfer;
use App\Actions\Finance\ShowBankTransfer;
use App\Http\Requests\Finance\FinanceReasonRequest;
use App\Http\Requests\Finance\RejectBankTransferRequest;
use App\Http\Resources\Finance\BankTransferDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class BankTransferDecisionController
{
    public function approve(FinanceReasonRequest $request, Payment $bankTransfer, #[CurrentUser] User $actor, ApproveBankTransfer $approve, ShowBankTransfer $show): JsonResponse
    {
        $approved = $approve->handle($actor, $bankTransfer, $request->reason());

        return ApiResponse::resource(new BankTransferDetailResource($show->handle($approved->refresh(), $actor)));
    }

    public function reject(RejectBankTransferRequest $request, Payment $bankTransfer, #[CurrentUser] User $actor, RejectBankTransfer $reject, ShowBankTransfer $show): JsonResponse
    {
        $rejected = $reject->handle($actor, $bankTransfer, $request->rejection(), $request->reason());

        return ApiResponse::resource(new BankTransferDetailResource($show->handle($rejected->refresh(), $actor)));
    }
}
