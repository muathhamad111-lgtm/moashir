<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\IssueExportDownload;
use App\Http\Resources\Files\SignedDownloadResource;
use App\Http\Resources\Finance\FinanceExportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Finance\FinanceExport;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class FinanceExportController
{
    public function show(FinanceExport $financeExport): JsonResponse
    {
        return ApiResponse::resource(new FinanceExportResource($financeExport));
    }

    public function download(FinanceExport $financeExport, #[CurrentUser] User $actor, IssueExportDownload $issueDownload): JsonResponse
    {
        return ApiResponse::resource(new SignedDownloadResource($issueDownload->handle($actor, $financeExport)));
    }
}
