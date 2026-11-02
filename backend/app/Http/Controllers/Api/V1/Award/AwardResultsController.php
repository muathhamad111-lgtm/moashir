<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Award;

use App\Actions\Award\ApproveNationalResults;
use App\Actions\Award\ApproveRegionResults;
use App\Actions\Award\ListAwardResults;
use App\Actions\Award\SetAwardRank;
use App\Actions\Award\UnapproveRegionResults;
use App\Enums\Award\RankStage;
use App\Http\Requests\Award\CycleActionRequest;
use App\Http\Requests\Award\IndexAwardResultsRequest;
use App\Http\Requests\Award\SetAwardRankRequest;
use App\Http\Requests\Award\UnapproveRegionRequest;
use App\Http\Resources\Award\AwardResultRowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use App\Support\Award\AwardResultsMeta;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class AwardResultsController
{
    public function __construct(private ListAwardResults $results) {}

    public function regional(IndexAwardResultsRequest $request, AwardCycle $cycle): JsonResponse
    {
        return $this->board($cycle, RankStage::Regional, $request->regionId());
    }

    public function national(AwardCycle $cycle): JsonResponse
    {
        return $this->board($cycle, RankStage::National, null);
    }

    public function rankRegional(SetAwardRankRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, SetAwardRank $rank): JsonResponse
    {
        return ApiResponse::resource(new AwardResultRowResource($rank->handle($actor, $cycle, $assessment, RankStage::Regional, $request->rank(), $request->reason())->load('assessment.entity', 'assessment.region')));
    }

    public function rankNational(SetAwardRankRequest $request, AwardCycle $cycle, Assessment $assessment, #[CurrentUser] User $actor, SetAwardRank $rank): JsonResponse
    {
        return ApiResponse::resource(new AwardResultRowResource($rank->handle($actor, $cycle, $assessment, RankStage::National, $request->rank(), $request->reason())->load('assessment.entity', 'assessment.region')));
    }

    public function approveRegion(CycleActionRequest $request, AwardCycle $cycle, Region $region, #[CurrentUser] User $actor, ApproveRegionResults $approve): JsonResponse
    {
        $approve->handle($actor, $cycle, $region, $request->reason());

        return $this->board($cycle, RankStage::Regional, $region->id);
    }

    public function unapproveRegion(UnapproveRegionRequest $request, AwardCycle $cycle, Region $region, #[CurrentUser] User $actor, UnapproveRegionResults $unapprove): JsonResponse
    {
        $unapprove->handle($actor, $cycle, $region, $request->reason());

        return $this->board($cycle, RankStage::Regional, $region->id);
    }

    public function approveNational(CycleActionRequest $request, AwardCycle $cycle, #[CurrentUser] User $actor, ApproveNationalResults $approve): JsonResponse
    {
        $approved = $approve->handle($actor, $cycle, $request->reason());

        return $this->board($approved, RankStage::National, null);
    }

    private function board(AwardCycle $cycle, RankStage $stage, ?int $regionId): JsonResponse
    {
        $board = $this->results->handle($cycle->refresh()->load('regions'), $stage, $regionId);

        return ApiResponse::data(AwardResultRowResource::collection($board->rankings)->resolve(request()), meta: AwardResultsMeta::of($board));
    }
}
