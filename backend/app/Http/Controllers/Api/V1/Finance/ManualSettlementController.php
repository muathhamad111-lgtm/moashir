<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ManuallySettlePayment;
use App\Http\Requests\Finance\ManuallySettlePaymentRequest;
use App\Http\Resources\Finance\TransactionDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ManualSettlementController
{
    public function __invoke(ManuallySettlePaymentRequest $request, Payment $payment, #[CurrentUser] User $actor, ManuallySettlePayment $settle): JsonResponse
    {
        $settled = $settle->handle($actor, $payment, $request->settlement());
        $settled->loadMissing(['refund', 'entity.owner']);

        return ApiResponse::resource(new TransactionDetailResource($settled));
    }
}
