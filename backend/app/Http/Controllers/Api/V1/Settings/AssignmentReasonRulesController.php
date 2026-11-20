<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Settings\UpdateAssignmentReasonRules;
use App\Http\Requests\Settings\UpdateAssignmentReasonRulesRequest;
use App\Http\Resources\Settings\AssignmentReasonRulesResource;
use App\Http\Responses\ApiResponse;
use App\Models\Identity\User;
use App\Services\Assessments\AssignmentReasonPolicy;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssignmentReasonRulesController
{
    public function show(AssignmentReasonPolicy $policy): JsonResponse
    {
        return ApiResponse::resource(new AssignmentReasonRulesResource($policy->rules()));
    }

    public function update(UpdateAssignmentReasonRulesRequest $request, #[CurrentUser] User $actor, UpdateAssignmentReasonRules $update, AssignmentReasonPolicy $policy): JsonResponse
    {
        $update->handle($actor, $request->rulesUpdate());

        return ApiResponse::resource(new AssignmentReasonRulesResource($policy->rules()));
    }
}
