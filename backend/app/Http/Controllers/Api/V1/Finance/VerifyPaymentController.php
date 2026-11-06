<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ShowMyPayment;
use App\Actions\Finance\VerifyPayment;
use App\Http\Requests\Finance\VerifyPaymentRequest;
use App\Http\Resources\Finance\OwnPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class VerifyPaymentController
{
    public function __invoke(VerifyPaymentRequest $request, Payment $payment, VerifyPayment $verifyPayment, ShowMyPayment $show): JsonResponse
    {
        $outcome = $verifyPayment->handle($payment, $request->providerPaymentId());

        return ApiResponse::resource(new OwnPaymentResource($show->handle($outcome->payment)), $outcome->isPending() ? Response::HTTP_ACCEPTED : Response::HTTP_OK, [
            'verification' => $outcome->result->value,
        ]);
    }
}
