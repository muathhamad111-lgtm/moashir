<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\DisqualifyAdvancedRequest;
use App\Actions\Award\QualifyAdvancedRequest;
use App\Http\Requests\Award\DisqualifyRequestRequest;
use App\Http\Requests\Award\QualifyRequestRequest;
use App\Http\Resources\Award\FilteringRowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class FilteringDecisionController
{
    private const array ROW_RELATIONS = ['region', 'latestFilteringDecision.decidedBy'];

    public function qualify(QualifyRequestRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, QualifyAdvancedRequest $qualify): JsonResponse
    {
        $qualified = $qualify->handle($actor, $cycle, $assessment, $request->instruction());

        return ApiResponse::resource(new FilteringRowResource($qualified->load(self::ROW_RELATIONS)));
    }

    public function disqualify(DisqualifyRequestRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, DisqualifyAdvancedRequest $disqualify): JsonResponse
    {
        $disqualified = $disqualify->handle($actor, $cycle, $assessment, $request->instruction());

        return ApiResponse::resource(new FilteringRowResource($disqualified->load(self::ROW_RELATIONS)));
    }
}
