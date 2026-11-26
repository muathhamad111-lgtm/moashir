<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ListTransactionEvents;
use App\Http\Resources\Finance\TransactionEventResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransactionEventController
{
    public function __invoke(Request $request, Payment $payment, ListTransactionEvents $listEvents): JsonResponse
    {
        $log = $listEvents->handle($payment);

        return ApiResponse::data(TransactionEventResource::collection($log->events)->resolve($request), meta: [
            'limit' => $log->limit,
            'has_more' => $log->hasMore,
        ]);
    }
}
