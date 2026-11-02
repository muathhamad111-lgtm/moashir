<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\IssueReceiptDownload;
use App\Actions\Finance\ReissueReceipt;
use App\Http\Requests\Finance\ReissueReceiptRequest;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Resources\Finance\ReceiptResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class FinanceReceiptController
{
    public function download(PaymentReceipt $receipt, #[CurrentUser] User $actor, IssueReceiptDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($actor, $receipt)));
    }

    public function reissue(ReissueReceiptRequest $request, PaymentReceipt $receipt, ReissueReceipt $reissueReceipt): JsonResponse
    {
        return ApiResponse::resource(new ReceiptResource($reissueReceipt->handle($receipt, $request->reason())));
    }
}
