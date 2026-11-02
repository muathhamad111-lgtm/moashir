<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\ReturnAdvancedForCompletion;
use App\Actions\Assessments\ShowRequest;
use App\Actions\Assessments\WithdrawAdvancedRequest;
use App\Http\Requests\Assessments\ReturnForCompletionRequest;
use App\Http\Requests\Assessments\WithdrawAdvancedRequestRequest;
use App\Http\Resources\Assessments\RequestDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AdvancedRequestController
{
    public function returnForCompletion(ReturnForCompletionRequest $request, Assessment $assessment, #[CurrentUser] User $actor, ReturnAdvancedForCompletion $return, ShowRequest $show): JsonResponse
    {
        return ApiResponse::resource(new RequestDetailResource($show->handle($actor, $return->handle($actor, $assessment, $request->instruction()))));
    }

    public function withdraw(WithdrawAdvancedRequestRequest $request, Assessment $assessment, #[CurrentUser] User $actor, WithdrawAdvancedRequest $withdraw, ShowRequest $show): JsonResponse
    {
        return ApiResponse::resource(new RequestDetailResource($show->handle($actor, $withdraw->handle($actor, $assessment, $request->reason()))));
    }
}
