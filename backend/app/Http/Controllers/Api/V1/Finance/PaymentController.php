<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\CreatePayment;
use App\Actions\Finance\ListMyPayments;
use App\Actions\Finance\ShowMyPayment;
use App\Actions\Finance\SubmitBankTransferPayment;
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
            'status_counts' => $listPayments->statusCountsOf($actor, $filters),
        ]);
    }

    public function store(StorePaymentRequest $request, #[CurrentUser] User $actor, CreatePayment $createPayment, SubmitBankTransferPayment $submitTransfer): JsonResponse
    {
        $created = $request->isBankTransfer()
            ? $submitTransfer->handle($actor, $request->bankTransferOrder())
            : $createPayment->handle($actor, $request->cardOrder(), app()->getLocale());

        return ApiResponse::resource(new CreatedPaymentResource($created), $created->isExistingOrder ? Response::HTTP_OK : Response::HTTP_CREATED, [
            'idempotent_replay' => $created->isReplay,
            'existing_order' => $created->isExistingOrder,
        ]);
    }

    public function show(Payment $payment, ShowMyPayment $show): JsonResponse
    {
        return ApiResponse::resource(new OwnPaymentResource($show->handle($payment)));
    }
}
