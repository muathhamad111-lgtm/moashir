<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\IssueReceiptDownload;
use App\Exceptions\Finance\ReceiptRejected;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Resources\Finance\ReceiptResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class PaymentReceiptController
{
    public function show(Payment $payment): JsonResponse
    {
        return ApiResponse::resource(new ReceiptResource($this->receiptOf($payment)));
    }

    public function download(Payment $payment, #[CurrentUser] User $actor, IssueReceiptDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($actor, $this->receiptOf($payment))));
    }

    private function receiptOf(Payment $payment): PaymentReceipt
    {
        return $payment->receipt ?? throw ReceiptRejected::notIssued();
    }
}
