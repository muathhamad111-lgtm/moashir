<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\CancelPaymentOrder;
use App\Actions\Finance\ShowMyPayment;
use App\Http\Resources\Finance\OwnPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use Illuminate\Http\JsonResponse;

final class CancelPaymentController
{
    public function __invoke(Payment $payment, CancelPaymentOrder $cancel, ShowMyPayment $show): JsonResponse
    {
        return ApiResponse::resource(new OwnPaymentResource($show->handle($cancel->handle($payment))));
    }
}
