<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Insights;

use App\Actions\Insights\GetMaturityAnalysis;
use App\Actions\Insights\GetStrengthsGaps;
use App\Actions\Insights\ShowExecutiveSummary;
use App\Http\Resources\Insights\ExecutiveSummaryResource;
use App\Http\Resources\Insights\MaturityResource;
use App\Http\Resources\Insights\StrengthsGapsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessmentInsightsController
{
    public function strengthsGaps(Assessment $assessment, GetStrengthsGaps $report): JsonResponse
    {
        return ApiResponse::resource(new StrengthsGapsResource($report->handle($assessment)));
    }

    public function maturity(Assessment $assessment, GetMaturityAnalysis $report): JsonResponse
    {
        return ApiResponse::resource(new MaturityResource($report->handle($assessment)));
    }

    public function executiveSummary(Assessment $assessment, #[CurrentUser] User $viewer, ShowExecutiveSummary $report): JsonResponse
    {
        return ApiResponse::resource(new ExecutiveSummaryResource($report->handle($viewer, $assessment)));
    }
}
