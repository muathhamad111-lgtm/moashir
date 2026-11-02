<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListPaymentEvents;
use App\Http\Requests\Finance\IndexPaymentEventsRequest;
use App\Http\Resources\Finance\PaymentEventResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PaymentEventController
{
    public function __invoke(IndexPaymentEventsRequest $request, ListPaymentEvents $listEvents): JsonResponse
    {
        return ApiResponse::paginated($listEvents->handle($request->filters()), PaymentEventResource::class);
    }
}
