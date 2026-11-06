<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\IssueTransferProofDownload;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class TransferProofController
{
    public function own(Payment $payment, #[CurrentUser] User $actor, IssueTransferProofDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($actor, $payment)));
    }

    public function staff(Payment $bankTransfer, #[CurrentUser] User $actor, IssueTransferProofDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($actor, $bankTransfer)));
    }
}
