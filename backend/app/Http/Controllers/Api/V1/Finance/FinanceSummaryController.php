<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\GetFinancialSummary;
use App\Http\Requests\Finance\FinanceSummaryRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class FinanceSummaryController
{
    public function __invoke(FinanceSummaryRequest $request, GetFinancialSummary $summary): JsonResponse
    {
        return ApiResponse::data($summary->handle($request->from(), $request->to())->toArray(), meta: ['live_only' => true]);
    }
}
