<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessments;

use App\Actions\Assessments\CompleteAccreditedAssessment;
use App\Actions\Assessments\CompleteInitialAssessment;
use App\Actions\Assessments\GetAssessorReview;
use App\Actions\Assessments\RequestCompletion;
use App\Actions\Assessments\SaveFeedbackItems;
use App\Actions\Assessments\SaveReviewScores;
use App\Actions\Assessments\StartReview;
use App\Http\Requests\Assessments\AssessmentActionRequest;
use App\Http\Requests\Assessments\RequestCompletionRequest;
use App\Http\Requests\Assessments\SaveFeedbackItemsRequest;
use App\Http\Requests\Assessments\SaveReviewScoresRequest;
use App\Http\Resources\Assessments\AssessmentResource;
use App\Http\Resources\Assessments\AssessmentScoresResource;
use App\Http\Resources\Assessments\AssessorReviewResource;
use App\Http\Resources\Assessments\FeedbackViewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AssessorReviewController
{
    public function show(Assessment $assessment, #[CurrentUser] User $viewer, GetAssessorReview $review): JsonResponse
    {
        return ApiResponse::resource(new AssessorReviewResource($review->handle($viewer, $assessment)));
    }

    public function start(AssessmentActionRequest $request, Assessment $assessment, #[CurrentUser] User $actor, StartReview $start): JsonResponse
    {
        return ApiResponse::resource(new AssessmentResource($start->handle($actor, $assessment, $request->reason())));
    }

    public function scores(SaveReviewScoresRequest $request, Assessment $assessment, #[CurrentUser] User $actor, SaveReviewScores $save): JsonResponse
    {
        return ApiResponse::resource(new AssessmentScoresResource($save->handle($actor, $assessment, $request->scores(), $request->reason())));
    }

    public function feedback(SaveFeedbackItemsRequest $request, Assessment $assessment, #[CurrentUser] User $actor, SaveFeedbackItems $save): JsonResponse
    {
        return ApiResponse::resource(new FeedbackViewResource($save->handle($actor, $assessment, $request->changes(), $request->reason())));
    }

    public function requestCompletion(RequestCompletionRequest $request, Assessment $assessment, #[CurrentUser] User $actor, RequestCompletion $requestCompletion): JsonResponse
    {
        return ApiResponse::resource(new AssessmentResource($requestCompletion->handle($actor, $assessment, $request->instruction())));
    }

    public function complete(
        AssessmentActionRequest $request,
        Assessment $assessment,
        #[CurrentUser] User $actor,
        CompleteAccreditedAssessment $completeAccredited,
        CompleteInitialAssessment $completeInitial,
    ): JsonResponse {
        $completed = $assessment->isAdvanced()
            ? $completeInitial->handle($actor, $assessment, $request->reason())
            : $completeAccredited->handle($actor, $assessment, $request->reason());

        return ApiResponse::resource(new AssessmentResource($completed));
    }
}
