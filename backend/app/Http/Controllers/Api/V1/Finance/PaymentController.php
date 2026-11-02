<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\CreatePayment;
use App\Actions\Finance\ListMyPayments;
use App\Http\Requests\Finance\IndexMyPaymentsRequest;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Http\Resources\Finance\CreatedPaymentResource;
use App\Http\Resources\Finance\OwnPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PaymentController
{
    public function index(IndexMyPaymentsRequest $request, #[CurrentUser] User $actor, ListMyPayments $listPayments): JsonResponse
    {
        $filters = $request->filters();

        return ApiResponse::paginated($listPayments->handle($actor, $filters), OwnPaymentResource::class, [
            'stats' => $listPayments->statsOf($actor, $filters)->toArray(),
        ]);
    }

    public function store(StorePaymentRequest $request, #[CurrentUser] User $actor, CreatePayment $createPayment): JsonResponse
    {
        $created = $createPayment->handle($actor, $request->purpose(), $request->idempotencyKey(), app()->getLocale());

        return ApiResponse::resource(new CreatedPaymentResource($created), $created->isExistingOrder ? Response::HTTP_OK : Response::HTTP_CREATED, [
            'idempotent_replay' => $created->isReplay,
            'existing_order' => $created->isExistingOrder,
        ]);
    }

    public function show(Payment $payment): JsonResponse
    {
        return ApiResponse::resource(new OwnPaymentResource($payment));
    }
}
