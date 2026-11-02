<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\ApplyForAdvanced;
use App\Actions\Assessments\DeleteAdvancedDraft;
use App\Actions\Assessments\GetAdvancedCurrent;
use App\Http\Requests\Assessments\ApplyForAdvancedRequest;
use App\Http\Requests\Assessments\AssessmentActionRequest;
use App\Http\Resources\Assessments\AdvancedCurrentResource;
use App\Http\Resources\Assessments\AssessmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AdvancedAssessmentController
{
    public function current(#[CurrentUser] User $actor, GetAdvancedCurrent $current): JsonResponse
    {
        return ApiResponse::resource(new AdvancedCurrentResource($current->handle($actor)));
    }

    public function apply(ApplyForAdvancedRequest $request, #[CurrentUser] User $actor, ApplyForAdvanced $apply): JsonResponse
    {
        $application = $apply->handle($actor, $request->cycleId());

        return ApiResponse::resource(new AssessmentResource($application->assessment), $application->created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(AssessmentActionRequest $request, Assessment $assessment, #[CurrentUser] User $actor, DeleteAdvancedDraft $delete): Response
    {
        $delete->handle($actor, $assessment, $request->reason());

        return ApiResponse::noContent();
    }
}
