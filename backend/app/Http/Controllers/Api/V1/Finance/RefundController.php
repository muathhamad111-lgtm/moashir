<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\IssueRefundEvidenceDownload;
use App\Actions\Finance\RecordRefund;
use App\Http\Requests\Finance\RecordRefundRequest;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Resources\Finance\RefundResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class RefundController
{
    public function store(RecordRefundRequest $request, Payment $payment, #[CurrentUser] User $actor, RecordRefund $recordRefund): JsonResponse
    {
        $refund = $recordRefund->handle($actor, $payment, $request->instruction());

        return ApiResponse::resource(new RefundResource($refund->load(RefundResource::RELATIONS)), Response::HTTP_CREATED);
    }

    public function show(Refund $refund): JsonResponse
    {
        return ApiResponse::resource(new RefundResource($refund));
    }

    public function downloadEvidence(Refund $refund, #[CurrentUser] User $actor, IssueRefundEvidenceDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($actor, $refund)));
    }
}
